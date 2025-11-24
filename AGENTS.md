# MISSION DIRECTIVE

Role: Staff Software Engineer.
Goal: Maintain a High-Fidelity Digital Wallet API using Event Sourcing, CQRS (Lite) & DDD.
Priorities: Strong Consistency (ACID), Idempotency, Code Quality (QA).

---

## 1. TECH STACK

- **Core:** PHP 8.3, Laravel 11.x.
- **Data:** MySQL 8.0 (Prod & Test), Redis (Cache & Queue).
- **PK Strategy:** ULID (`HasUlids` trait). NO auto-increments. NO UUIDv4.
- **Quality:** Pest PHP, PHPStan (Level 5+), Laravel Pint, CaptainHook.

---

## 2. ARCHITECTURE & PATTERNS

### A. Write Model (The Hard Part)

- **Aggregates:** `App\Domain\Wallet\WalletAggregate`. Pure PHP class. Handles math & invariant checks.
- **Repository:** `App\Repositories\WalletRepository`. Handles Event Stream & Projections. Uses `EventSerializer`.
- **Transaction Flow (Must follow):**
  1. Start `DB::transaction`.
  2. **Pessimistic Lock:** `Wallet::lockForUpdate()` (ordered IDs to prevent deadlocks in P2P).
  3. **Compliance Check:** Verify Daily Limits (Config: `config/wallet.php`).
  4. **Rehydrate:** Load events from `stored_events`, replay in Aggregate.
  5. **Action:** Execute method -> Returns new Event DTO.
  6. **Persist:** Save Event (`stored_events`) AND Update Projection (`wallets`).
  7. **Async:** Dispatch Webhook Job (`SendTransactionNotification`).
  8. Commit.

### B. Read Model

- **Source:** Table `wallets` (Projection updated synchronously).
- **Usage:** Simple `SELECT` for `GET /balance`. No replay needed for read.

### C. Idempotency

- **Middleware:** `App\Http\Middleware\CheckIdempotency`.
- **Rule:** Required for `POST`. Persists to Redis (Fast) and MySQL `idempotency_keys` (Audit).
- **Behavior:** Hit returns cached JSON + header `X-Idempotency-Hit`.

---

## 3. CODE STANDARDS

- **Money:** Integer always (Cents). No floats.
- **Strict Typing:** `declare(strict_types=1);`. Use specialized Exceptions (e.g., `InsufficientFundsException`).
- **Controller Safety:** Catch Domain Exceptions -> Return 400. Do NOT catch unexpected errors (Let 500 handler catch it).
- **Webhooks:** URL stored in `users.webhook_url`. Handled by Queue Worker.

## 4. DIRECTORY MAP

```text
app/
├── Domain/Wallet/       # PURE DOMAIN
│   ├── Events/          # DTOs: FundsDeposited, TransferSent...
│   ├── Exceptions/
│   └── WalletAggregate.php
├── Http/
│   ├── Controllers/     # WalletController, AuthController
│   ├── Requests/        # Auth/*, Wallet/* (Strict Validation)
│   └── Middleware/      # CheckIdempotency
├── Infrastructure/      # Serializers
├── Jobs/                # SendTransactionNotification (Webhooks)
├── Models/              # User, Wallet, StoredEvent
├── Repositories/        # WalletRepository
└── Services/            # WalletTransactionService (Orchestrator)
```

---

## 5. DATABASE SCHEMA (Actual)

We performed a cleanup. Only these migrations are active:

1. `...create_users_table.php` (Includes `webhook_url` string).
2. `...create_jobs_table.php` (Only `failed_jobs`).
3. `...create_wallet_domain_tables.php` (wallets, stored_events, idempotency_keys).

*Tables Removed:* sessions, cache, personal_access_tokens, job_batches.

---

## 6. DEVELOPMENT COMMANDS (Makefile)

- **Setup:** `make setup` (Full install + DB Creation).
- **Reset:** `make reset-db` (Fresh migrations + Seeds users `sender`/`receiver`).
- **Test:** `make test` (Runs Pest).
- **QA:** `make check` (Runs Lint + PHPStan + Test).
- **Concurrency:** `make race` (Bash script calling curl in parallel).
- **Deep Clean:** `make clean` (Wipes docker volumes).

## 7. DOCUMENTATION

- **OpenAPI:** Removed L5-Swagger (Code bloat).
- **Source of Truth:** `insomnia_wallet_api.json` (Import into Insomnia).
- **Limits:** `WALLET_LIMIT_DAILY_DEPOSIT` & `WALLET_LIMIT_DAILY_WITHDRAWAL` in `.env`.
