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