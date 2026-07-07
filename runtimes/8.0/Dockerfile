FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG PHP_EXTENSIONS=""

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite mysql-client postgresql-client \
    php80 php80-cli php80-pdo php80-pdo_mysql php80-pdo_pgsql \
    php80-mbstring php80-xml php80-curl php80-zip php80-bcmath \
    php80-intl php80-gd php80-redis php80-opcache php80-tokenizer \
    php80-fileinfo php80-ctype php80-session php80-dom php80-phar \
    php80-pcntl php80-posix \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && addgroup -g ${WWWGROUP} sail 2>/dev/null || true \
    && adduser -D -G sail -u 1337 sail \
    && mkdir -p /etc/supervisor/conf.d

COPY php.ini /etc/php80/php.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80
ENTRYPOINT ["start-container"]

FROM base AS development
RUN apk add --no-cache nodejs npm php80-pecl-xdebug \
    && cp /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf.bak 2>/dev/null || true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

FROM base AS production
RUN apk add --no-cache --virtual .build-deps && true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
