# Backlog de Execução: Wallet API (Event Sourcing)

## 🚀 Fase 1: Fundação e Infraestrutura (Docker & Setup)
**Objetivo:** Ter o ambiente rodando (PHP + MySQL + Redis) e padrões de projeto definidos.

- [ ] **Task 1.1: Docker Compose Config**
    - Criar `docker-compose.yml` com serviços: `app` (build customizado), `db` (MySQL 8.0), `redis`, `queue` (worker).
    - Criar `Dockerfile` instalando extensões: `pdo_mysql`, `bcmath`, `pcntl`, `redis`.
    - Criar arquivo `.env.example` configurado para Docker.
- [ ] **Task 1.2: Setup Inicial Laravel**
    - Instalar Laravel 11 novo.
    - Configurar pacotes: `pestphp/pest`, `pestphp/pest-plugin-laravel`, `php-open-source-saver/jwt-auth`.
    - Gerar chave JWT: `php artisan jwt:secret`.
- [ ] **Task 1.3: Context Rules (Agentes)**
    - Criar arquivo `AGENTS.md` na raiz (conforme discutido) para guiar a IA nas próximas tarefas.

---

## 💾 Fase 2: Dados e Identidade (Migrations & Auth)
**Objetivo:** Definir esquema de banco e sistema de login seguro.

- [ ] **Task 2.1: Models Base e ULID**
    - Configurar Models para usar `HasUlids` (não usar auto-increment).
    - Definir schema `users` (id, name, email, password).
- [ ] **Task 2.2: Schema Event Sourcing**
    - Criar Migration `wallets` (Read Model): id (ULID), user_id, balance (BigInt/Centavos), version (lock).
    - Criar Migration `stored_events` (Write Model): id, aggregate_id, event_class, payload (json), occurred_at.
    - Criar Migration `idempotency_keys`: key, response, status_code.
- [ ] **Task 2.3: Módulo de Autenticação**
    - Implementar `AuthController`: `register`, `login`, `refresh`, `me`.
    - **Importante:** No `register`, disparar um evento nativo do Laravel ou criar a `Wallet` (saldo 0) para o usuário novo.

---

## 🧠 Fase 3: O "Kernel" (Domínio & Event Sourcing)
**Objetivo:** Implementar a lógica de negócios pura, desacoplada de Framework.

- [ ] **Task 3.1: Event DTOs (Domain)**
    - Criar classes em `App\Domain\Wallet\Events`:
        - `WalletCreated`, `FundsDeposited`, `FundsWithdrawn`, `TransferSent`, `TransferReceived`.
    - Todos devem ser `readonly` e conter apenas dados.
- [ ] **Task 3.2: WalletAggregate (Regras)**
    - Criar classe `App\Domain\Wallet\WalletAggregate`.
    - Método `retrieve(uuid, events)`: Reconstrói estado.
    - Métodos Actions: `deposit(amount)`, `withdraw(amount)` com validações (throw Exceptions se < 0).
    - Método interno `apply($event)`: Muda o `$this->balance`.

---

## ⚙️ Fase 4: Aplicação e Serviços (A "Cola")
**Objetivo:** Conectar o Banco de Dados ao Domínio usando transações ACID.

- [ ] **Task 4.1: Infraestrutura de Eventos**
    - Criar `WalletRepository`: métodos para salvar events na `stored_events` e atualizar a `wallets` table.
- [ ] **Task 4.2: WalletTransactionService (Depósito/Saque)**
    - Criar método `performTransaction`.
    - Lógica: DB Begin -> Lock For Update (`wallet`) -> Load Events -> Replay -> Execute Domain Action -> Store Event -> Update Read Model -> Commit.
- [ ] **Task 4.3: Service de Transferência (Complexo)**
    - Método `transferFunds(from, to, amount)`.
    - Deve envolver ambos os agregados na mesma transação DB.
    - Garantir atomicidade: Só commita se ambos (débito e crédito) funcionarem.

---

## 🔌 Fase 5: API Pública e Padronização
**Objetivo:** Expor os serviços via HTTP REST.

- [ ] **Task 5.1: Response Standardization**
    - Criar Trait/Classe `ApiResponse` para padronizar JSON `{ data: ..., message: ... }`.
    - Configurar Handler de Erros global (Exceptions de Domínio -> Erro 400).
- [ ] **Task 5.2: Validations (Requests)**
    - Criar `DepositRequest`, `WithdrawRequest`, `TransferRequest`.
    - Validar: Integer apenas, Min: 1 (1 centavo).
- [ ] **Task 5.3: Controllers da Carteira**
    - Implementar Endpoints usando o `WalletTransactionService`.
    - GET `/balance`, GET `/transactions`.
    - POST `/deposit`, `/withdraw`, `/transfer`.

---

## 🛡 Fase 6: Robustez e Diferenciais
**Objetivo:** Tornar o sistema à prova de falhas e cobrir requisitos extras.

- [ ] **Task 6.1: Idempotency Middleware**
    - Implementar Middleware que checa header `Idempotency-Key`.
    - Salvar resposta no Redis (cache tag ou key simples).
- [ ] **Task 6.2: Webhooks Async**
    - Criar Job `SendWebhookNotification`.
    - Disparar job após sucesso do Service de Transferência.
- [ ] **Task 6.3: Feature & Unit Tests**
    - Unit Test: `WalletAggregate` calculando saldo.
    - Feature Test: Fluxo Depósito -> Transferência -> Saque.
    - **Hardcore Test:** Race Condition Test (Disparar 5 saques assíncronos e verificar se saldo respeitou o limite).
- [ ] **Task 6.4: Documentação**
    - Gerar `README.md` final (baseado no template aprovado).
    - Exportar Collection Postman/Insomnia (opcional, JSON file).

---

### Sugestão de Ordem para IA

Ao comandar o Agente, passe um bloco de cada vez.

1.  **Comando 1:** "Execute as tarefas da Fase 1. Foque apenas nos arquivos de configuração Docker e setup do Laravel."
2.  **Comando 2:** "Execute a Fase 2. Crie as migrations exatamente com esses campos."
3.  ... e assim por diante. Isso evita que ele gere um código enorme e desconexo.