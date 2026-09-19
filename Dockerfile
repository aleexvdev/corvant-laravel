FROM php:8.3-cli

# System libs required to build the PHP extensions below
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpq-dev \
        libicu-dev \
    && docker-php-ext-install pdo_mysql pdo_pgsql pgsql bcmath intl zip \
    # redis has no core/apt package; it ships only as a PECL extension
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["bash"]
