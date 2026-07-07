FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG MYSQL_CLIENT=mysql-client
ARG HAS_MYSQL=0
ARG HAS_POSTGRES=0
ARG HAS_MONGO=0
ARG HAS_REDIS=0
ARG HAS_MEMCACHED=0

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite \
    php85 php85-cli php85-pdo \
    php85-mbstring php85-xml php85-curl php85-zip php85-bcmath \
    php85-intl php85-gd php85-opcache php85-tokenizer \
    php85-fileinfo php85-ctype php85-session php85-dom php85-phar \
    php85-pcntl php85-posix \
    && if [ "$HAS_MYSQL" = "1" ]; then apk add --no-cache ${MYSQL_CLIENT} php85-pdo_mysql; fi \
    && if [ "$HAS_POSTGRES" = "1" ]; then apk add --no-cache postgresql-client php85-pdo_pgsql; fi \
    && if [ "$HAS_REDIS" = "1" ]; then apk add --no-cache php85-redis; fi \
    && if [ "$HAS_MEMCACHED" = "1" ]; then apk add --no-cache php85-pecl-memcached memcached-dev; fi \
    && if [ "$HAS_MONGO" = "1" ]; then apk add --no-cache php85-pecl-mongodb; fi \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && addgroup -g ${WWWGROUP} sail 2>/dev/null || true \
    && adduser -D -G sail -u 1337 sail \
    && mkdir -p /etc/supervisor/conf.d

COPY php.ini /etc/php85/php.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80
ENTRYPOINT ["start-container"]

FROM base AS development
RUN apk add --no-cache nodejs npm php85-pecl-xdebug \
    && cp /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf.bak 2>/dev/null || true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

FROM base AS production
