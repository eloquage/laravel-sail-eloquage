FROM alpine:3.21 AS base

ARG WWWGROUP=1000
ARG PHP_EXTENSIONS=""

ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"

WORKDIR /var/www/html

RUN apk add --no-cache \
    bash curl git supervisor gosu sqlite mysql-client postgresql-client \
    php82 php82-cli php82-pdo php82-pdo_mysql php82-pdo_pgsql \
    php82-mbstring php82-xml php82-curl php82-zip php82-bcmath \
    php82-intl php82-gd php82-redis php82-opcache php82-tokenizer \
    php82-fileinfo php82-ctype php82-session php82-dom php82-phar \
    php82-pcntl php82-posix \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && addgroup -g ${WWWGROUP} sail 2>/dev/null || true \
    && adduser -D -G sail -u 1337 sail \
    && mkdir -p /etc/supervisor/conf.d

COPY php.ini /etc/php82/php.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80
ENTRYPOINT ["start-container"]

FROM base AS development
RUN apk add --no-cache nodejs npm php82-pecl-xdebug \
    && cp /etc/supervisor/conf.d/supervisord.conf /etc/supervisor/conf.d/supervisord.conf.bak 2>/dev/null || true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

FROM base AS production
RUN apk add --no-cache --virtual .build-deps && true
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
