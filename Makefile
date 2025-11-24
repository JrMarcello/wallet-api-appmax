# Define que estes comandos não são arquivos físicos
.PHONY: setup up down bash logs reset-db init-db test race lint lint-check analyse check clean-infra

# --- 🚀 Setup & Infraestrutura ---

# Executa o setup completo do zero (Primeiro uso)
setup:
	@echo "🚀 Iniciando setup..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	
	# Sobe os containers
	docker-compose up -d --build
	
	@echo "📦 Instalando dependências (Composer)..."
	docker-compose exec app composer install

	@echo "🔄 Reiniciando aplicação..."
	docker-compose restart app

	@echo "⏳ Aguardando MySQL inicializar..."
	@sleep 10

	$(MAKE) init-db

	@echo "🔑 Gerando chaves..."
	docker-compose exec app php artisan key:generate --force
	docker-compose exec app php artisan jwt:secret --force
	
	@echo "💾 Migrando banco principal..."
	docker-compose exec app php artisan migrate:fresh --force

	@echo "✅ Setup concluído! API: http://localhost:8000/api"

# Reseta o banco de dados (Mantém containers rodando)
reset-db:
	@echo "🧨 Resetando bancos..."
	$(MAKE) init-db
	
	@echo "💾 Migrando banco principal..."
	docker-compose exec app php artisan migrate:fresh --force
	
	@echo "🧹 Limpando cache e chaves de idempotência..."
	docker-compose exec app php artisan cache:clear
	
	@echo "✅ Reset concluído!"

# Limpeza Profunda: Remove containers, redes e VOLUMES
clean:
	@echo "💥 Destruindo infraestrutura Docker (Containers + Volumes)..."
	docker-compose down -v --remove-orphans
	@echo "✅ Infraestrutura limpa. Rode 'make setup' para recriar."

# --- 🐳 Docker Controls ---

up:
	docker-compose up -d

down:
	docker-compose down

bash:
	docker-compose exec app bash

logs:
	docker-compose logs -f

# --- 🛠️ Helpers Internos ---

# Inicializa Bancos e Permissões (Idempotente)
init-db:
	@echo "📦 Configurando MySQL (Criando Databases e Grants)..."
	@docker-compose exec db mysql -u root -proot -e "\
		CREATE DATABASE IF NOT EXISTS wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		CREATE DATABASE IF NOT EXISTS wallet_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		GRANT ALL PRIVILEGES ON wallet.* TO 'walletuser'@'%'; \
		GRANT ALL PRIVILEGES ON wallet_test.* TO 'walletuser'@'%'; \
		FLUSH PRIVILEGES;"

# --- 🧪 Qualidade & Testes ---

test:
	docker-compose exec app ./vendor/bin/pest

race:
	@echo "🏎️  Preparando pista de corrida (Race Condition Test)..."
	@chmod +x tests/race_test.sh
	@./tests/race_test.sh

lint:
	@echo "🎨 Formatando código com Pint..."
	docker-compose exec app ./vendor/bin/pint

lint-check:
	@echo "🎨 Verificando estilo de código..."
	docker-compose exec app ./vendor/bin/pint --test

analyse:
	@echo "🔍 Rodando análise estática (PHPStan)..."
	docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G

# Roda tudo (O comando "Antes do Push")
check: lint analyse test
	@echo "✅ Tudo certo! Pode commitar."