# Executa o setup completo do zero (instalação limpa)
setup:
	@echo "🚀 Iniciando setup do ambiente..."
	@if [ ! -f .env ]; then cp .env.example .env; fi
	docker-compose up -d --build
	@echo "📦 Instalando dependências..."
	docker-compose exec app composer install
	@echo "🔑 Gerando chaves de segurança..."
	docker-compose exec app php artisan key:generate --force
	docker-compose exec app php artisan jwt:secret --force
	@echo "💾 Rodando migrações do banco..."
	docker-compose exec app php artisan migrate:fresh --force
	@echo "✅ Setup concluído! Acesse: http://localhost:8000"

# Sobe os containers
up:
	docker-compose up -d

# Desliga os containers
down:
	docker-compose down

# Roda os testes
test:
	docker-compose exec app ./vendor/bin/pest

# Acessa o terminal do container
bash:
	docker-compose exec app bash

# Roda os logs
logs:
	docker-compose logs -f

# Reseta o banco de dados e limpa o cache (Cuidado: Apaga tudo!)
reset:
	@echo "🧨 Resetando banco de dados..."
	docker-compose exec app php artisan migrate:fresh --force
	@echo "🧹 Limpando cache e chaves de idempotência..."
	docker-compose exec app php artisan cache:clear
	@echo "✅ Reset concluído! Lembre-se de criar um novo usuário."

# Roda o teste de concorrência (Stress Test via Bash)
race:
	@echo "🏎️  Preparando pista de corrida (Race Condition Test)..."
	@chmod +x tests/race_test.sh
	@./tests/race_test.sh