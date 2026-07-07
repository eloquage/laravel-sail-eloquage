FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG PHP_EXTENSIONS=""

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite mysql-client postgresql-client \
    php85 php85-cli php85-pdo php85-pdo_mysql php85-pdo_pgsql \
    php85-mbstring php85-xml php85-curl php85-zip php85-bcmath \
    php85-intl php85-gd php85-redis php85-opcache php85-tokenizer \
    php85-fileinfo php85-ctype php85-session php85-dom php85-phar \
    php85-pcntl php85-posix \
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
RUN apk add --no-cache --virtual .build-deps && true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
