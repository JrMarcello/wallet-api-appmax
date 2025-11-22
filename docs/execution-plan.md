# Implementação Wallet API

**Stack:** PHP 8.2+, Laravel 11, MySQL 8, Redis, Docker.
**Key Patterns:** Event Sourcing (Write), CQRS (Leve), Domain-Driven Design (Aggregates), Optimistic Locking, Idempotency.

Execute o projeto seguindo rigorosamente este roteiro:

---

## MÓDULO 1: Infraestrutura (Docker & Database)

### 1. Estratégia de Chaves Primárias
**Decisão:** NÃO use auto-incremento nem UUID v4.
**Implementação:** Utilize **ULIDs** (Universally Unique Lexicographically Sortable Identifier) em todos os Models. Use a trait `Illuminate\Database\Eloquent\Concerns\HasUlids` do Laravel 11. Isso evita fragmentação de índice no MySQL.

### 2. Docker Compose
Gere um `docker-compose.yml` robusto contendo:
*   **app:** Dockerfile customizado (Instalar: `pdo_mysql`, `bcmath`, `pcntl`, `redis`). Expor porta 8000.
*   **db:** Imagem `mysql:8.0`. Volume persistente. Variáveis via `.env`.
*   **redis:** Imagem `redis:alpine` (Cache e Queue).
*   **queue:** Um container rodando `php artisan queue:work` para processar os Webhooks.

### 3. Banco de Dados (MySQL)
Schema Config:
*   **Charset:** `utf8mb4`, **Collation:** `utf8mb4_unicode_ci`.
*   Todas as colunas de valores monetários (`amount`, `balance`) devem ser `BIGINT` (representando centavos). **Nunca use FLOAT/DOUBLE.**

---

## MÓDULO 2: Padronização da API (Foundation)

### 1. Envelopamento de Resposta (API Standards)
Não retorne arrays soltos. Crie uma Trait ou Classe `App\Http\Responses\ApiResponse`.
Estrutura Obrigatória do JSON:
```json
{
  "status": "success" | "error",
  "code": 200, // Espelho do HTTP Status
  "message": "Human readable message",
  "data": { ... }, // O payload real (null em caso de erro)
  "errors": { ... }, // Validations messages (apenas em erro)
  "meta": { ... } // Pagination or Idempotency debug info
}
```

### 2. Tratamento Global de Exceções
Modifique o `bootstrap/app.php` ou ExceptionHandler para capturar:
*   `ValidationException` -> HTTP 422 (Estruturado no campo "errors").
*   `ModelNotFoundException` -> HTTP 404.
*   `DomainException` (nossa classe de regras de negócio) -> HTTP 400.
*   `Throwable` (Erro 500 genérico) -> HTTP 500 (Esconder stack trace em prod).

---

## MÓDULO 3: Domain Core (Event Sourcing Puro)

**Diretório:** `app/Domain/Wallet/`

### 1. Entidades e Agregados
*   Use classes puras PHP. **WalletAggregate NÃO deve extender Eloquent Model.**
*   **Métodos de Agregado:** Devem validar regras de negócio ("Saldo insuficiente") *antes* de retornar o evento.

### 2. Store de Eventos (Migration `stored_events`)
```php
Schema::create('stored_events', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('aggregate_id')->index(); // ID da Wallet
    $table->string('event_class');
    $table->json('payload');
    $table->timestamp('occurred_at'); // Utilize para re-ordenar se necessário
    // Add Unique Constraint (aggregate_id + version) se formos implementar optimistic lock real nos eventos
});
```

### 3. View Materializada (Migration `wallets`)
Tabela apenas para leitura rápida (Snapshot).
```php
Schema::create('wallets', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->foreignUlid('user_id')->constrained();
    $table->bigInteger('balance')->default(0);
    $table->unsignedBigInteger('version')->default(0); // Para Optimistic Locking na escrita do snapshot
    $table->timestamps();
});
```

---

## MÓDULO 4: Camada de Aplicação (Use Cases)

### 1. Validações (FormRequests)
Para cada endpoint de escrita, crie um Request dedicado. Ex: `MakeDepositRequest`.
*   `amount`: `['required', 'integer', 'min:1']`.
*   Regra: Proibir valores decimais no input para forçar front-end a mandar centavos, OU use um middleware que converte (R$ 10,50 -> 1050) e valide como integer. Preferência por Integer explícito.

### 2. Service Transactional (Orquestração)
O `WalletTransactionService` deve ser o **único** ponto de escrita.
Algoritmo Crítico (Ex: Saque):
```php
DB::transaction(function () use ($walletId, $amount) {
    // 1. Lock pessimista na linha da wallet (Snapshot) para serializar requisições concorrentes
    $walletView = WalletModel::lockForUpdate()->find($walletId);

    // 2. Carregar eventos passados e reconstruir Agregado (Fonte da Verdade)
    $events = EventRepository::getByAggregateId($walletId);
    $aggregate = WalletAggregate::retrieve($walletId, $events);

    // 3. Tentar executar a ação (Regra de Negócio no Domínio)
    // Se falhar (sem saldo), o Agregado lança Exception e rollback ocorre
    $newEvent = $aggregate->withdraw($amount);

    // 4. Persistir novo evento
    EventRepository::store($newEvent);

    // 5. Atualizar View Materializada (Sync Projection)
    $walletView->balance -= $amount;
    $walletView->save();
});
```
*Nota: O Lock `forUpdate` no snapshot resolve o problema de "Replay custoso" ao mesmo tempo que garante serialização do DB. É um "atalho pragmático" dentro do ES.*

---

## MÓDULO 5: Funcionalidades Extras

1.  **Idempotency:** Middleware obrigatório nas rotas POST (`deposit`, `withdraw`, `transfer`). Armazene o hash da resposta no Redis por 24h.
2.  **Webhooks Assíncronos:** O fluxo de transferência (`TransferProcessedEvent`) deve disparar um Listener. O Listener coloca um Job (`SendTransactionNotification`) na fila `redis`.

---

## Roteiro de Output (Code Generation)

Gere o código na seguinte ordem exata:

1.  **Arquivos de Config:** `docker-compose.yml`, `Dockerfile`.
2.  **Core:** Migrations e Models (User, Wallet, StoredEvent).
3.  **Domain:** Event DTOs e a classe `WalletAggregate`.
4.  **Service Layer:** `WalletTransactionService` (Implementando lógica de Transaction + Lock + Replay + Save).
5.  **Foundation:** `ApiResponse` trait, `CheckIdempotency` Middleware e Requests.
6.  **HTTP:** Controllers (`AuthController`, `TransactionController`) e Rotas (`api.php`).
7.  **Testes:** Um teste `Feature` completo simulando um cenário de "Race Condition" (corrida) onde 2 saques simultâneos são feitos, garantindo que o saldo não fique negativo incorretamente.