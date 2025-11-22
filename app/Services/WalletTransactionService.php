<?php

namespace App\Services;

use App\Domain\Wallet\WalletAggregate;
use App\Models\Wallet;
use App\Repositories\WalletRepository;
use Illuminate\Database\DatabaseManager;
use Exception;

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
        return $this->db->transaction(function () use ($userId, $amount) {
            // 1. Lock & Load: Buscamos o ID da carteira travando a linha
            // Isso impede que outro processo altere essa carteira agora (Race Condition)
            $walletModel = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $walletId = $walletModel->id;

            // 2. Event Sourcing: Reconstruir o estado
            $history = $this->repository->getHistory($walletId);
            $aggregate = WalletAggregate::retrieve($walletId, $history);

            // 3. Domain Logic: Executar ação
            $newEvent = $aggregate->deposit($amount);

            // 4. Persistência: Salvar evento + Atualizar Projeção
            $this->repository->append($newEvent);
            $this->repository->updateProjection($walletId, $aggregate->getBalance()); // Saldo novo já calculado

            return [
                'wallet_id' => $walletId,
                'new_balance' => $aggregate->getBalance(),
                'transaction_id' => $walletId // Poderíamos retornar o ID do evento também
            ];
        });
    }

    /**
     * Executa um Saque Atômico
     */
    public function withdraw(string $userId, int $amount): array
    {
        return $this->db->transaction(function () use ($userId, $amount) {
            // 1. Lock Pessimista
            $walletModel = Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $walletId = $walletModel->id;

            // 2. Replay
            $history = $this->repository->getHistory($walletId);
            $aggregate = WalletAggregate::retrieve($walletId, $history);

            // 3. Domain Logic (Aqui pode estourar Exception de Saldo Insuficiente)
            $newEvent = $aggregate->withdraw($amount);

            // 4. Persistência
            $this->repository->append($newEvent);
            $this->repository->updateProjection($walletId, $aggregate->getBalance());

            return [
                'wallet_id' => $walletId,
                'new_balance' => $aggregate->getBalance()
            ];
        });
    }
    
    /**
     * Consulta de Saldo Rápida (Sem replay, direto da projeção)
     */
    public function getBalance(string $userId): int 
    {
        return Wallet::where('user_id', $userId)->value('balance') ?? 0;
    }
}
