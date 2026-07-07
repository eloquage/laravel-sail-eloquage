FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG PHP_EXTENSIONS=""

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite mysql-client postgresql-client \
    php81 php81-cli php81-pdo php81-pdo_mysql php81-pdo_pgsql \
    php81-mbstring php81-xml php81-curl php81-zip php81-bcmath \
    php81-intl php81-gd php81-redis php81-opcache php81-tokenizer \
    php81-fileinfo php81-ctype php81-session php81-dom php81-phar \
    php81-pcntl php81-posix \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && addgroup -g ${WWWGROUP} sail 2>/dev/null || true \
    && adduser -D -G sail -u 1337 sail \
    && mkdir -p /etc/supervisor/conf.d

COPY php.ini /etc/php81/php.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80
ENTRYPOINT ["start-container"]

FROM base AS development
RUN apk add --no-cache nodejs npm php81-pecl-xdebug \
    && cp /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf.bak 2>/dev/null || true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

FROM base AS production
RUN apk add --no-cache --virtual .build-deps && true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
