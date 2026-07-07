FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG PHP_EXTENSIONS=""

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite mysql-client postgresql-client \
    php84 php84-cli php84-pdo php84-pdo_mysql php84-pdo_pgsql \
    php84-mbstring php84-xml php84-curl php84-zip php84-bcmath \
    php84-intl php84-gd php84-redis php84-opcache php84-tokenizer \
    php84-fileinfo php84-ctype php84-session php84-dom php84-phar \
    php84-pcntl php84-posix \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && addgroup -g ${WWWGROUP} sail 2>/dev/null || true \
    && adduser -D -G sail -u 1337 sail \
    && mkdir -p /etc/supervisor/conf.d

COPY php.ini /etc/php84/php.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80
ENTRYPOINT ["start-container"]

FROM base AS development
RUN apk add --no-cache nodejs npm php84-pecl-xdebug \
    && cp /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf.bak 2>/dev/null || true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

FROM base AS production
RUN apk add --no-cache --virtual .build-deps && true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
