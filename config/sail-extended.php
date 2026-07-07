<?php

return [

    'default_php_version' => '8.5',

    'php_versions' => ['8.2', '8.3', '8.4', '8.5'],

    'manifest_path' => 'docker/.sail-extended.json',

    'images' => [
        'registry' => 'ghcr.io/eloquage/laravel-sail-eloquage',
        'tag_prefix' => 'php',
        'full_stack_dependencies' => true,
    ],

    'compose_files' => [
        'development' => 'compose.yaml',
        'production' => 'compose.yaml.production',
    ],

    'octane' => [
        'servers' => ['frankenphp', 'swoole', 'roadrunner'],
        'default_server' => 'frankenphp',
        'roadrunner_version' => '2025.1.1',
        'images' => [
            'frankenphp' => [
                '8.2' => 'dunglas/frankenphp:php8.2',
                '8.3' => 'dunglas/frankenphp:php8.3',
                '8.4' => 'dunglas/frankenphp:php8.4',
                '8.5' => 'dunglas/frankenphp:php8.5',
            ],
            'swoole' => [
                '8.2' => 'phpswoole/swoole:php8.2-alpine',
                '8.3' => 'phpswoole/swoole:php8.3-alpine',
                '8.4' => 'phpswoole/swoole:php8.4-alpine',
                '8.5' => 'phpswoole/swoole:php8.5-alpine',
            ],
            'roadrunner' => [
                '8.2' => 'php:8.2-cli-alpine3.21',
                '8.3' => 'php:8.3-cli-alpine3.21',
                '8.4' => 'php:8.4-cli-alpine3.21',
                '8.5' => 'php:8.5-cli-alpine3.21',
            ],
        ],
    ],

    'services' => [
        'pgsql' => [
            'versions' => ['18', '17', '16', '15'],
            'default_version' => '18',
            'pgvector' => true,
            'replication' => true,
        ],
        'mysql' => [
            'versions' => ['8.4', '8.0'],
            'default_version' => '8.4',
            'pgvector' => false,
            'replication' => true,
        ],
        'mariadb' => [
            'versions' => ['11', '10.11'],
            'default_version' => '11',
            'pgvector' => false,
            'replication' => true,
        ],
        'mongodb' => [
            'versions' => ['8', '7'],
            'default_version' => '8',
            'pgvector' => false,
            'replication' => true,
        ],
        'redis' => [
            'versions' => ['8', '7'],
            'default_version' => '8',
            'pgvector' => false,
            'replication' => true,
        ],
        'valkey' => [
            'versions' => ['8', '7'],
            'default_version' => '8',
            'pgvector' => false,
            'replication' => true,
        ],
        'meilisearch' => [
            'versions' => ['v1.12', 'v1.11', 'latest'],
            'default_version' => 'v1.12',
            'pgvector' => false,
            'replication' => false,
        ],
        'typesense' => [
            'versions' => ['27.1', '26.0'],
            'default_version' => '27.1',
            'pgvector' => false,
            'replication' => true,
        ],
    ],

    'sail_services' => [
        'mysql',
        'pgsql',
        'mariadb',
        'mongodb',
        'redis',
        'valkey',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'rustfs',
        'mailpit',
        'rabbitmq',
        'selenium',
        'soketi',
    ],

    'default_services' => ['mysql', 'redis', 'selenium', 'mailpit'],

    'package_managers' => ['bun', 'pnpm', 'npm'],

    'supervisor' => [
        'defaults' => [
            'schedule' => false,
            'horizon' => false,
            'vite' => false,
        ],
    ],

];
