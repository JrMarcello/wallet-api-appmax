# Sobe os containers
up:
	docker-compose up -d

# Desliga os containers
down:
	docker-compose down

# Acessa o terminal do container
bash:
	docker-compose exec app bash

# Roda os logs
logs:
	docker-compose logs -f

# Executa o setup completo do zero (instalação limpa)
setup:
	@echo "🚀 Iniciando setup..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker-compose up -d --build

	@echo "⏳ Aguardando MySQL inicializar..."
	@sleep 10

	@echo "📦 Criando bancos e permissões..."
	docker-compose exec db mysql -u root -proot -e "\
		CREATE DATABASE IF NOT EXISTS wallet_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		GRANT ALL PRIVILEGES ON wallet_test.* TO 'walletuser'@'%'; \
		FLUSH PRIVILEGES;"

	@echo "📦 Instalando dependências..."
	docker-compose exec app composer install
	@echo "🔑 Gerando chaves..."
	docker-compose exec app php artisan key:generate --force
	docker-compose exec app php artisan jwt:secret --force
	@echo "💾 Migrando banco principal..."
	docker-compose exec app php artisan migrate:fresh --force
	@echo "✅ Setup concluído!"

# Reseta o banco de dados e limpa o cache (Cuidado: Apaga tudo!)
reset:
	@echo "🧨 Resetando bancos (principal e teste)..."
	docker-compose exec db mysql -u root -proot -e "\
		CREATE DATABASE IF NOT EXISTS wallet_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; \
		GRANT ALL PRIVILEGES ON wallet_test.* TO 'walletuser'@'%'; \
		FLUSH PRIVILEGES; \
		CREATE DATABASE IF NOT EXISTS wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

	@echo "💾 Migrando banco principal..."
	docker-compose exec app php artisan migrate:fresh --force

	@echo "💾 Migrando banco de teste..."
	docker-compose exec app php artisan migrate:fresh --database=mysql --force --env=testing

	@echo "🧹 Limpando cache e chaves de idempotência..."
	docker-compose exec app php artisan cache:clear

	@echo "✅ Reset concluído! Lembre-se de criar um novo usuário."

# Roda os testes
test:
	docker-compose exec app ./vendor/bin/pest

# Roda o teste de concorrência (Stress Test via Bash)
race:
	@echo "🏎️  Preparando pista de corrida (Race Condition Test)..."
	@chmod +x tests/race_test.sh
	@./tests/race_test.sh

# --- Quality Assurance ---

# Formata o código automaticamente (Laravel Pint)
lint:
	@echo "🎨 Formatando código com Pint..."
	docker-compose exec app ./vendor/bin/pint

# Apenas verifica formatação (para CI)
lint-check:
	@echo "🎨 Verificando estilo de código..."
	docker-compose exec app ./vendor/bin/pint --test

# Roda análise estática (PHPStan)
analyse:
	@echo "🔍 Rodando análise estática (PHPStan)..."
	docker-compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G

# Roda tudo (Testes + Lint + Análise) - O comando "Antes do Push"
check: lint analyse test
	@echo "✅ Tudo certo! Pode commitar."
