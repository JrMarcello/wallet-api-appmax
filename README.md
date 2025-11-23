# Wallet API (Staff Engineer Case)

![CI/CD](https://github.com/jrmarcello/wallet-api-appmax/actions/workflows/ci-cd.yml/badge.svg)
![PHP 8.3](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php)
![Laravel 11](https://img.shields.io/badge/Laravel-11-FF2D20?style=flat-square&logo=laravel)
![Architecture](https://img.shields.io/badge/Architecture-Event%20Sourcing-blueviolet?style=flat-square)
![Tests](https://img.shields.io/badge/Tests-Pest-purple?style=flat-square)

Uma API de Carteira Digital de alta fidelidade, projetada para demonstrar robustez financeira, concorrência segura e padrões de arquitetura escaláveis (**Event Sourcing, CQRS Lite, DDD**).

---

## 🚀 Quick Start

Todo o ambiente é containerizado. Você só precisa de **Docker** e **Make** instalados.

1. **Clone o repositório:**

   ```bash
   git clone https://github.com/jrmarcello/wallet-api-appmax.git
   cd wallet-api-appmax
   ```

2. **Execute o Setup Automático:**
   Este comando sobe os containers, instala dependências, configura Git Hooks, gera chaves, roda migrações e cria o banco de testes.

   ```bash
   make setup
   ```

3. **Acesse a API:**
   * URL Base: `http://localhost:8000/api`
   * Status Check: `curl http://localhost:8000/up`

---

## 🧠 Decisões de Arquitetura

O projeto foi construído para resolver problemas reais de sistemas financeiros, indo além de um CRUD tradicional.

### 1. Event Sourcing (Core)

* **Write Model:** Tabela `stored_events`. Fonte da verdade imutável.
* **Read Model:** Tabela `wallets`. Projeção síncrona para leitura rápida de saldo.
* **Por que:** Garante auditabilidade total e permite replay de transações. A lógica matemática reside no Agregado (`WalletAggregate`), isolada do framework.

### 2. Concorrência & Integridade

* **Pessimistic Locking:** Uso de `lockForUpdate()` com ordenação de IDs no MySQL para prevenir **Race Conditions** e **Deadlocks** em transferências simultâneas.
* **Transações Atômicas:** Tudo (Evento, Projeção, Webhook Dispatch) ocorre dentro de uma transação ACID.

### 3. Resiliência & Idempotência

* **Idempotency Key:** Middleware que intercepta o header `Idempotency-Key`. Requests duplicados (retries de rede) retornam a resposta original cacheada (Redis + DB Audit) sem duplicar a operação financeira.
* **Async Webhooks:** Notificações são enviadas via **Fila (Redis)**, garantindo que a API responda rápido enquanto o processamento pesado ocorre em background com retries automáticos.

---

## 🛠️ Comandos Úteis (Makefile)

Simplificamos a interação com o Docker através do `make`. Não é necessário decorar comandos longos.

| Comando | Descrição |
| :--- | :--- |
| `make setup` | **Primeiro uso.** Instala tudo do zero e configura hooks. |
| `make up` | Sobe os containers (App, DB, Redis, Queue). |
| `make down` | Para os containers. |
| `make reset-db` | **Reseta o DB**, limpa cache e roda Seeds (cria users padrão). |
| `make test` | Roda a suíte completa de testes (Unit + Feature). |
| `make race` | **Bônus:** Roda script de Stress Test para validar concorrência. |
| `make check` | Roda Lint (Pint), Análise Estática (PHPStan) e Testes (CI local). |
| `make logs` | Acompanha logs da aplicação e workers em tempo real. |
| `make clean` | Derruba tudo e **apaga volumes** (hard reset). |

---

## ⚓️ Git Hooks & Qualidade

Este projeto utiliza **CaptainHook** para garantir a qualidade do código antes mesmo do Code Review ("Shift Left").

* **Commit Msg:** Valida se a mensagem segue o padrão *Conventional Commits* (ex: `feat: add login`).
* **Pre-Commit:** Executa automaticamente `make lint-check` e `make analyse`. O commit é bloqueado se houver erros de estilo ou tipagem.
* **Pre-Push:** Executa toda a suíte de testes (`make test`).

> *Os hooks são instalados automaticamente ao rodar `make setup`.*

---

## 🧪 Testes Automatizados

O projeto possui cobertura rigorosa utilizando **Pest PHP**.

1. **Unitários:** Validam a matemática do Agregado (Domain) isoladamente.
2. **Feature (E2E):** Validam o fluxo completo (Cadastro -> Depósito -> Transferência) e regras de negócio.
3. **Análise Estática:** **PHPStan Nível 5** + Larastan para garantir tipagem forte.
4. **Stress Test:** Um script Bash (`tests/race_test.sh`) que dispara requisições paralelas via cURL para validar o sistema de Locks contra Race Conditions.

Para rodar o check-up completo:

```bash
make check
```

---

## 🔌 API Endpoints & Insomnia

Na raiz do projeto, encontra-se o arquivo **`insomnia_wallet_api.json`**.

### Como usar no Insomnia

1. Vá em **Dashboard** -> **Create** -> **Import from File**.
2. Selecione o arquivo JSON na raiz deste projeto.
3. No canto superior direito, selecione o ambiente **"Local Development"**.
4. Ao fazer Login (Request 3), copie o token retornado e cole na variável de ambiente `access_token`.

### Resumo de Rotas

#### Auth (JWT)

* `POST /auth/register` - Cria usuário e carteira.
* `POST /auth/login` - Retorna Token.
* `POST /auth/refresh` - Rotaciona token (Blacklist no anterior).
* `POST /auth/webhook` - Configura URL para notificação.

#### Wallet

> ⚠️ **Nota:** Operações de escrita exigem header `Idempotency-Key: <uuid>`. A collection do Insomnia gera isso automaticamente.

* `GET  /wallet/balance`
* `GET  /wallet/transactions` (Extrato via Event Replay)
* `POST /wallet/deposit` (Aceita apenas inteiros/centavos)
* `POST /wallet/withdraw`
* `POST /wallet/transfer` (Transação P2P Atômica)

---

## ⚙️ CI/CD (GitHub Actions)

O projeto conta com uma pipeline configurada em `.github/workflows/ci-cd.yml` que executa automaticamente em PRs para a `main`:

1. **Build & Setup:** Sobe serviços (MySQL/Redis).
2. **Quality Gate:** Roda `Pint` (Lint) e `PHPStan` (Análise Estática).
3. **Testing:** Executa a suíte `Pest` com banco de testes dedicado.
4. **Delivery:** Se tudo passar, constrói a imagem Docker e publica no **GitHub Container Registry**.

---

## 🧩 Fluxo Lógico Interno

O diagrama abaixo ilustra o ciclo de vida de uma **Transferência**, demonstrando como garantimos consistência e performance simultaneamente.

```mermaid
sequenceDiagram
    autonumber
    actor Client
    participant API as API / Middleware
    participant Service as TransactionService
    participant DB as MySQL (Lock & Store)
    participant Domain as WalletAggregate
    participant Queue as Redis Queue

    Client->>API: POST /transfer {amount: 100}
    Note over API: Verifica Idempotency (Cache)

    API->>Service: Inicia Orquestração
    Service->>DB: BEGIN TRANSACTION
    
    Note right of Service: Concorrência Pessimista
    Service->>DB: SELECT ... FOR UPDATE (Lock Ordenado)
    Service->>DB: Busca histórico de eventos
    
    Service->>Domain: Replay (Eventos) -> Reconstrói Estado
    Domain->>Domain: Valida Regras (Saldo Suficiente?)
    Domain-->>Service: Retorna Novos Eventos (Sent/Received)
    
    par Persistência Dual
        Service->>DB: INSERT stored_events (Write Model)
        Service->>DB: UPDATE wallets (Read Model / Projeção)
    end
    
    Service->>Queue: Dispatch Webhook Job (Assíncrono)
    
    Service->>DB: COMMIT
    Service-->>API: Retorna Sucesso
    
    Note over API: Salva Idempotency Key
    API-->>Client: 200 OK
```

**Autor:** Marcelo Jr
