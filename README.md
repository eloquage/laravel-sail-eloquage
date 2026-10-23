# Laravel Sail Extended

![Laravel Sail Extended container and runtime banner](art/banner.png)

Laravel Sail Extended extends Sail's `sail:install`, `sail:add`, and `sail:publish` commands. It scaffolds Docker Compose services and PHP runtime files for standard PHP, FrankenPHP, Swoole, and RoadRunner projects.

## Requirements

- PHP 8.4 or newer
- Laravel 11, 12, or 13
- Laravel Sail 1.63 or newer
- Docker Compose

## Install

```bash
composer require eloquage/laravel-sail-extended
```

Laravel package discovery registers the commands. Generate a PHP 8.5 development setup with MySQL and Redis:

```bash
php artisan sail:install --with=mysql,redis --php=8.5 --no-interaction
./vendor/bin/sail up -d
```

The command writes Compose files, runtime Dockerfiles, and a `.sail-extended.json` manifest under `docker/`.

## Add services or choose a runtime

Add PostgreSQL with pgvector to an existing Sail Extended setup:

```bash
php artisan sail:add pgsql --pgsql-version=18 --pgsql-pgvector
```

Generate an Octane setup using FrankenPHP:

```bash
php artisan sail:install --with=mysql,redis --php=8.5 --octane --octane-server=frankenphp
```

Supported services include MySQL, PostgreSQL, MariaDB, MongoDB, Redis, Valkey, Memcached, Meilisearch, Typesense, MinIO, RustFS, Mailpit, RabbitMQ, Selenium, and Soketi. PostgreSQL, MySQL, MariaDB, MongoDB, Redis, Valkey, and Typesense also have replication templates.

Use `php artisan sail:install --help` or `php artisan sail:add --help` to see all options. The commands are interactive by default; pass `--no-interaction` and explicit service/runtime options for scripted use.

## Configuration

Publish the package config when you need to change defaults:

```bash
php artisan vendor:publish --tag=sail-extended-config
```

The config file controls the default PHP version, Octane server, service versions, registry image, and Compose file names. Review generated Compose files and environment settings before using them in a deployment.

## Tests

```bash
composer install
composer test
composer analyse
composer format -- --test
```

## License

MIT. See [LICENSE.md](LICENSE.md).
