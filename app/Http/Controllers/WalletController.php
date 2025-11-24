<?php

namespace App\Http\Controllers;

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Http\Requests\Wallet\MakeDepositRequest;
use App\Http\Requests\Wallet\MakeTransferRequest;
use App\Http\Requests\Wallet\MakeWithdrawRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use App\Repositories\WalletRepository;
use App\Services\WalletTransactionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected WalletTransactionService $service,
        protected WalletRepository $repository
    ) {}

    public function balance(): JsonResponse
    {
        $userId = auth('api')->id();
        $balance = $this->service->getBalance($userId);

        return $this->success([
            'balance' => $balance,
            'currency' => 'BRL_CENTS',
        ]);
    }

    public function deposit(MakeDepositRequest $request): JsonResponse
    {
        try {
            $result = $this->service->deposit(
                auth('api')->id(),
                $request->amount
            );

            return $this->success($result, 'Depósito realizado com sucesso.');

        } catch (InsufficientFundsException|InvalidArgumentException $e) {
            // Tratamos erro de limite diário ou valor inválido como 400
            return $this->error($e->getMessage(), 400);
        }
    }

    public function withdraw(MakeWithdrawRequest $request): JsonResponse
    {
        try {
            $result = $this->service->withdraw(
                auth('api')->id(),
                $request->amount
            );

            return $this->success($result, 'Saque realizado com sucesso.');

        } catch (InsufficientFundsException|InvalidArgumentException $e) {
            // Tratamos erro de domínio (Saldo ou Valor) como Bad Request (400)
            return $this->error($e->getMessage(), 400);
        }
    }

    public function transfer(MakeTransferRequest $request): JsonResponse
    {
        try {
            $targetUser = User::where('email', $request->target_email)->firstOrFail();
            $result = $this->service->transfer(
                auth('api')->id(),
                $targetUser->id,
                $request->amount
            );

            return $this->success($result, 'Transferência realizada com sucesso.');

        } catch (ModelNotFoundException $e) {
            // Destinatário não existe -> 404
            return $this->error('Destinatário não encontrado.', 404);

        } catch (InsufficientFundsException|InvalidArgumentException $e) {
            // Regras de Negócio (Saldo, Auto-transferência) -> 400
            return $this->error($e->getMessage(), 400);
        }
    }

    public function transactions(): JsonResponse
    {
        /** @var User $user */
        $user = auth('api')->user();

        // Previne erro caso usuário não tenha carteira
        if (! $user->wallet) {
            return $this->error('Carteira não encontrada.', 404);
        }

        $formattedHistory = array_map(
            function ($event) {
                return [
                    'type' => class_basename($event),
                    'amount' => $event->amount,
                    // Formato ISO seguro ou amigável
                    'date' => $event->occurredAt->format('Y-m-d H:i:s'),
                    'details' => match (class_basename($event)) {
                        'TransferSent' => ['to' => $event->targetWalletId],
                        'TransferReceived' => ['from' => $event->sourceWalletId],
                        default => []
                    },
                ];
            },
            $this->repository->getHistory($user->wallet->id)
        );

        return $this->success(
            // Retorna o histórico de transações formatado
            array_map(
                function ($event) {
                    return [
                        'type' => class_basename($event),
                        'amount' => $event->amount,
                        // Formato ISO seguro ou amigável
                        'date' => $event->occurredAt->format('Y-m-d H:i:s'),
                        'details' => match (class_basename($event)) {
                            'TransferSent' => ['to' => $event->targetWalletId],
                            'TransferReceived' => ['from' => $event->sourceWalletId],
                            default => []
                        },
                    ];
                },
                $this->repository->getHistory($user->wallet->id)
            )
        );
    }
}
