# MISSION DIRECTIVE
You are an expert Staff Software Engineer acting as the primary maintainer of this project. 
This is NOT a standard CRUD application. It is a High-Fidelity Financial Wallet API using **Event Sourcing** and **CQRS**.

All generated code must adhere strictly to the constraints defined below. Prioritize data consistency, atomic transactions, and idempotent behavior over implementation speed.

---

## 1. TECH STACK & ENVIRONMENT
- **Language:** PHP 8.2+ (Strict Types Enabled)
- **Framework:** Laravel 11.x
- **Database:** MySQL 8.0 (Production) / Redis (Cache & Queue)
- **Primary Keys:** ULID (via `Illuminate\Database\Eloquent\Concerns\HasUlids`). NO UUIDv4. NO Auto-increment integers.
- **Testing:** Pest PHP (`tests/Feature` mainly).

---

## 2. ARCHITECTURAL RULES (STRICT)

### A. Domain Driven Design (DDD)
1.  **Aggregates (Write Model):** Located in `app/Domain/Wallet`.
    - Must be **Pure PHP** classes. DO NOT extend Eloquent models.
    - DO NOT depend on the database or container.
    - Logic for business rules (e.g., "Insufficient Funds") resides ONLY here.
    - Must accept `History` (events), reconstitute state, and return a *New Event*.
2.  **Projections (Read Model):** Located in `app/Models`.
    - These are "dumb" snapshots for fast reading (e.g., `Wallet` model with `balance` column).
    - They are updated *synchronously* within the transaction flow to ensure Strong Consistency for the user.
3.  **Controllers:**
    - Must be "skinny". Never contain business logic.
    - Only orchestrate: Validate Input -> Call Service -> Return DTO/Resource.

### B. Event Sourcing Protocol
We do not mutate the "source of truth" directly.
1.  **Source of Truth:** The `stored_events` table.
2.  **Read Optimizations:** The `wallets` table (is just a cache).
3.  **Transaction Flow:**
    - Start DB Transaction.
    - Lock `wallets` row (Read Model) for Update (Pessimistic Lock).
    - Load history from `stored_events` for this aggregate.
    - Replay Aggregate to memory.
    - Execute Command on Aggregate -> Receive `NewEvent`.
    - Persist `NewEvent` to `stored_events`.
    - Project/Update `wallets` table balance.
    - Commit Transaction.

### C. Idempotency
- **Middleware:** `App\Http\Middleware\CheckIdempotency`.
- **Rule:** Any `POST` to `/deposit`, `/withdraw`, or `/transfer` MUST have an `Idempotency-Key` header.
- **Mechanism:** 
  - Key exists? Return cached response (status + json). 
  - Key missing? Execute -> Cache Result (TTL 24h).

---

## 3. CODING STANDARDS

### Data & Money
- **Integers Only:** All monetary values are in *centavos* (cents).
- **Pattern:** `100` = R$ 1.00.
- **Validation:** Never accept floats from API input. Reject decimals.

### Error Handling
- Use `App\Http\Responses\ApiResponse` for standardized JSON envelopes.
- Throw Custom Exceptions (`InsufficientFundsException`) and map them to HTTP 400/422 in `bootstrap/app.php`.

### Naming Conventions
- **Events:** Verbs in Past Tense (`FundsDeposited`, `TransferSent`).
- **Controllers:** `ResourceActionController` (e.g., `WalletDepositController`) is preferred over monolithic controllers.
- **Tables:** Plural (`users`, `wallets`, `stored_events`).

---

## 4. FILE STRUCTURE MAP

```text
app/
├── Domain/              <-- PURE DOMAIN (Write Logic)
│   └── Wallet/
│       ├── Events/      <-- Immutable DTOs
│       ├── WalletAggregate.php
│       └── Services/    <-- Complex Orchestration (Transfer)
├── Infrastructure/
│   └── Services/        <-- Third-party impl (Mail, S3)
├── Http/
│   ├── Controllers/     <-- Input entry points
│   ├── Requests/        <-- Strict Validation
│   └── Responses/       <-- JSON Standardization
└── Models/              <-- READ MODELS (Eloquent)
```

---

## 5. TESTING STRATEGY (PEST)

Every new feature must include a **Feature Test**.
**Scenario Requirements:**
1.  **Happy Path:** assert DB `wallets` updated AND `stored_events` created.
2.  **Invariant Violation:** assert `withdraw(balance + 1)` throws Exception and DB is untouched.
3.  **Race Condition (Crucial):** Use logical process locking logic (mocks) to simulate concurrent requests ensuring negative balance never happens.

## 6. SPECIFIC "DON'Ts" FOR AI
- **DO NOT** add methods like `increment()` or `decrement()` directly on the Wallet Controller without generating an event.
- **DO NOT** forget `DB::transaction` on financial operations.
- **DO NOT** use `float` type hints. Use `int`.
