FROM php:8.3-cli

WORKDIR /var/www

# Instala dependências do SO
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip netcat-openbsd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Instala extensões PHP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instala Redis
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configura usuário
RUN useradd -G www-data,root -u 1000 -d /home/dev dev
RUN mkdir -p /home/dev/.composer && chown -R dev:dev /home/dev

# Copia o código fonte para dentro da imagem
COPY . /var/www

# Ajusta permissões
RUN chown -R dev:dev /var/www

# Define o usuário para execução
USER dev

# Instala dependências do projeto (Vendor)
# Isso garante que a imagem funcione sozinha (standalone)
# O .env.example é copiado para .env apenas para o build não quebrar sem chave
RUN cp .env.example .env && \
    composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]