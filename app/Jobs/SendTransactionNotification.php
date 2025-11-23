<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTransactionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30];

    public function __construct(
        public string $payeeId,
        public int $amount
    ) {}

    public function handle(): void
    {
        $ctx = ['payee_user_id' => $this->payeeId, 'amount' => $this->amount];

        $user = User::select('webhook_url')->find($this->payeeId);
        if (! $user || ! $user->webhook_url) {
            Log::info("Usuário {$this->payeeId} não tem webhook configurado.", $ctx);

            return;
        }

        $ctx['url'] = $user->webhook_url;
        Log::info('Webhook Job: Attempting delivery', $ctx);

        try {
            $response = Http::timeout(5)->post($user->webhook_url, [
                'event' => 'transfer_received',
                'amount' => $this->amount,
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($response->successful()) {
                Log::info("Webhook enviado: {$user->webhook_url}");
                Log::info('Webhook enviado', array_merge($ctx, [
                    'status_code' => $response->status(),
                ]));
            } else {
                Log::warning('Webhook Job: Remote Error', array_merge($ctx, [
                    'status_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 200),
                ]));
                // Lança exceção para usar o mecanismo de retry do Laravel
                throw new \Exception('Status: '.$response->status());
            }
        } catch (\Exception $e) {
            Log::error('Webhook Job: Connection Failed', array_merge($ctx, [
                'error' => $e->getMessage(),
            ]));
            throw $e; // Garante retry
        }
    }
}
