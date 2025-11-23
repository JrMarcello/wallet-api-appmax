<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // READ MODEL (Snapshot para leitura rápida)
        Schema::create('wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            // DINHEIRO: Inteiros sempre (centavos). Ex: 1000 = R$ 10.00
            $table->bigInteger('balance')->default(0);
            // CONTROLE DE CONCORRÊNCIA: Optimistic Locking
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        // WRITE MODEL (Fonte da Verdade Imutável)
        Schema::create('stored_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('aggregate_id')->index();
            $table->string('event_class');
            $table->json('payload');
            $table->unsignedBigInteger('aggregate_version')->nullable();
            // Timestamp crítico para ordenação
            $table->timestamp('occurred_at', precision: 6);
            $table->timestamp('created_at')->useCurrent();

            // Índice composto para garantir que recuperar o histórico seja O(1)
            $table->index(['aggregate_id', 'occurred_at']);
        });

        // Tabela para persistir Chaves de Idempotência (para auditoria)
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->ulid('user_id')->index()->nullable();
            $table->text('response_json');
            $table->integer('status_code');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('stored_events');
        Schema::dropIfExists('wallets');
    }
};
