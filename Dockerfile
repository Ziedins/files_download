FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    curl \
    zip \
    libzip-dev \
    && docker-php-ext-install zip

WORKDIR /app

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock* ./

RUN composer install --no-interaction --no-scripts

COPY . .

RUN curl -sS https://get.symfony.com/cli/installer | bash && \
    mv ~/.symfony*/bin/symfony /usr/local/bin/symfony

ENTRYPOINT []
CMD []
