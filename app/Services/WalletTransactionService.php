<?php

namespace App\Services;

use App\Domain\Wallet\WalletAggregate;
use App\Models\Wallet;
use App\Repositories\WalletRepository;
use Exception;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

class WalletTransactionService
{
    public function __construct(
        protected WalletRepository $repository,
        protected DatabaseManager $db
    ) {}

    /**
     * Executa um Depósito Atômico
     */
    public function deposit(string $userId, int $amount): array
    {
        $ctx = ['user_id' => $userId, 'amount' => $amount, 'operation' => 'deposit'];
        Log::info('Wallet Deposit: Start', $ctx);

        try {
            return $this->db->transaction(function () use ($userId, $amount, $ctx) {
                $walletModel = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
                $walletId = $walletModel->id;

                $ctx['wallet_id'] = $walletId;

                $history = $this->repository->getHistory($walletId);
                $aggregate = WalletAggregate::retrieve($walletId, $history);

                $newEvent = $aggregate->deposit($amount);

                $this->repository->append($newEvent);
                $this->repository->updateProjection($walletId, $aggregate->getBalance());

                Log::info('Wallet Deposit: Success', array_merge($ctx, [
                    'new_balance' => $aggregate->getBalance(),
                ]));

                return [
                    'wallet_id' => $walletId,
                    'new_balance' => $aggregate->getBalance(),
                    'transaction_id' => $walletId,
                ];
            });
        } catch (Exception $e) {
            Log::error('Wallet Deposit: Failed - '.$e->getMessage(), $ctx);
            throw $e;
        }
    }

    /**
     * Executa um Saque Atômico
     */
    public function withdraw(string $userId, int $amount): array
    {
        $ctx = ['user_id' => $userId, 'amount' => $amount, 'operation' => 'withdraw'];
        Log::info('Wallet Withdraw: Start', $ctx);

        try {
            return $this->db->transaction(function () use ($userId, $amount, $ctx) {
                $walletModel = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
                $walletId = $walletModel->id;
                $ctx['wallet_id'] = $walletId;

                $history = $this->repository->getHistory($walletId);
                $aggregate = WalletAggregate::retrieve($walletId, $history);

                $newEvent = $aggregate->withdraw($amount);

                $this->repository->append($newEvent);
                $this->repository->updateProjection($walletId, $aggregate->getBalance());

                Log::info('Wallet Withdraw: Success', array_merge($ctx, [
                    'new_balance' => $aggregate->getBalance(),
                ]));

                return [
                    'wallet_id' => $walletId,
                    'new_balance' => $aggregate->getBalance(),
                ];
            });
        } catch (Exception $e) {
            Log::error('Wallet Withdraw: Failed - '.$e->getMessage(), $ctx);
            throw $e;
        }
    }

    public function getBalance(string $userId): int
    {
        return Wallet::where('user_id', $userId)->value('balance') ?? 0;
    }

    /**
     * Executa Transferência P2P Atômica
     */
    public function transfer(string $payerUserId, string $payeeUserId, int $amount): array
    {
        $ctx = [
            'payer_user_id' => $payerUserId,
            'payee_user_id' => $payeeUserId,
            'amount' => $amount,
            'operation' => 'transfer_p2p',
        ];

        Log::info('P2P Transfer: Start', $ctx);

        if ($payerUserId === $payeeUserId) {
            Log::warning('P2P Transfer: Blocked (Self Transfer)', $ctx);
            throw new \InvalidArgumentException('Cannot transfer to self');
        }

        try {
            return $this->db->transaction(function () use ($payerUserId, $payeeUserId, $amount, $ctx) {
                // Lock Strategy
                $payerWalletId = Wallet::where('user_id', $payerUserId)->value('id');
                $payeeWalletId = Wallet::where('user_id', $payeeUserId)->value('id');

                if (! $payerWalletId || ! $payeeWalletId) {
                    throw new \Exception('One or both users do not have a wallet configured.');
                }

                $idsToLock = [$payerWalletId, $payeeWalletId];
                sort($idsToLock);

                // Mostra a ordem do Lock. Vital para debugar Deadlocks.
                Log::debug('P2P Transfer: Acquiring Locks', ['sorted_lock_ids' => $idsToLock]);

                $lockedWallets = Wallet::whereIn('id', $idsToLock)->lockForUpdate()->get();

                // Replay
                $payerHistory = $this->repository->getHistory($payerWalletId);
                $payerAggregate = WalletAggregate::retrieve($payerWalletId, $payerHistory);

                $payeeHistory = $this->repository->getHistory($payeeWalletId);
                $payeeAggregate = WalletAggregate::retrieve($payeeWalletId, $payeeHistory);

                $eventSent = $payerAggregate->sendTransfer($payeeWalletId, $amount);
                $eventReceived = $payeeAggregate->receiveTransfer($payerWalletId, $amount);

                // Persistência
                $this->repository->append($eventSent);
                $this->repository->updateProjection($payerWalletId, $payerAggregate->getBalance());

                $this->repository->append($eventReceived);
                $this->repository->updateProjection($payeeWalletId, $payeeAggregate->getBalance());

                // Webhook
                Log::info('P2P Transfer: Dispatching Webhook Job', ['payee_user_id' => $payeeUserId]);
                \App\Jobs\SendTransactionNotification::dispatch($payeeUserId, $amount);

                Log::info('P2P Transfer: Success', array_merge($ctx, [
                    'payer_wallet_id' => $payerWalletId,
                    'payee_wallet_id' => $payeeWalletId,
                    'final_payer_balance' => $payerAggregate->getBalance(),
                ]));

                return [
                    'transaction_id' => $payerWalletId.'-'.time(),
                    'payer_balance' => $payerAggregate->getBalance(),
                    'payee_id' => $payeeUserId,
                ];
            });
        } catch (Exception $e) {
            Log::error('P2P Transfer: Failed - '.$e->getMessage(), $ctx);
            throw $e;
        }
    }
}
