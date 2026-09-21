FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libssl-dev libcurl4-openssl-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql pcntl posix mbstring \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY ensure-hosts.sh /opt/ydadmin/ensure-hosts.sh
COPY entrypoint.sh /opt/ydadmin/entrypoint.sh
RUN chmod +x /opt/ydadmin/ensure-hosts.sh /opt/ydadmin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 8000 8001
ENTRYPOINT ["/opt/ydadmin/entrypoint.sh"]
