<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\MakeDepositRequest;
use App\Http\Requests\Wallet\MakeTransferRequest;
use App\Http\Requests\Wallet\MakeWithdrawRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Repositories\WalletRepository;
use App\Services\WalletTransactionService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletTransactionService $service,
        protected WalletRepository $repository
    ) {}

    /**
     * GET /api/wallet/balance
     */
    public function balance(): JsonResponse
    {
        $userId = auth('api')->id();
        $balance = $this->service->getBalance($userId);

        return $this->success([
            'balance' => $balance,
            'currency' => 'BRL_CENTS',
        ]);
    }

    /**
     * POST /api/wallet/deposit
     */
    public function deposit(MakeDepositRequest $request): JsonResponse
    {
        try {
            $result = $this->service->deposit(
                auth('api')->id(),
                $request->amount
            );

            return $this->success($result, 'Depósito realizado com sucesso.');
        } catch (\Exception $e) {
            // Em prod, logariamos o erro real e retornariamos msg genérica
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/wallet/withdraw
     */
    public function withdraw(MakeWithdrawRequest $request): JsonResponse
    {
        try {
            $result = $this->service->withdraw(
                auth('api')->id(),
                $request->amount
            );

            return $this->success($result, 'Saque realizado com sucesso.');
        } catch (\Exception $e) {
            // Captura o "InsufficientFundsException" do domínio
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/wallet/transfer
     */
    public function transfer(MakeTransferRequest $request): JsonResponse
    {
        try {
            // Resolvendo Email -> ID do destino
            $targetUser = User::where('email', $request->target_email)->firstOrFail();

            $result = $this->service->transfer(
                auth('api')->id(), // Payer
                $targetUser->id,   // Payee
                $request->amount
            );

            return $this->success($result, 'Transferência realizada com sucesso.');
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * GET /api/wallet/transactions
     * Retorna o "Extrato" (Replay dos Eventos)
     */
    public function transactions(): JsonResponse
    {
        $walletId = auth('api')->user()->wallet->id;

        // Usamos o Repository para buscar os eventos puros
        $history = $this->repository->getHistory($walletId);

        // Transformamos os objetos de evento em Array para JSON
        $formattedHistory = array_map(function ($event) {
            return [
                'type' => class_basename($event), // "FundsDeposited"
                'amount' => $event->amount,
                'date' => $event->occurredAt->format('Y-m-d H:i:s'),
                'details' => match (class_basename($event)) {
                    'TransferSent' => ['to' => $event->targetWalletId],
                    'TransferReceived' => ['from' => $event->sourceWalletId],
                    default => []
                },
            ];
        }, $history);

        return $this->success($formattedHistory);
    }
}
