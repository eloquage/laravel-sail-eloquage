<?php

use Illuminate\Support\Facades\Artisan;

it('installs compose files with development and production targets', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));
    $composeProduction = file_get_contents($this->fixturePath('compose.yaml.production'));

    expect($compose)
        ->toContain('target: development')
        ->toContain('dockerfile: Dockerfile')
        ->toContain('context: ./docker/8.5')
        ->not->toContain('OCTANE_BASE_TAG');

    expect($composeProduction)
        ->toContain('target: production')
        ->toContain('dockerfile: Dockerfile');
});

it('installs full stack compose with registry images by default', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'mysql,pgsql,mongodb,redis,memcached',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));
    $manifest = json_decode(file_get_contents($this->fixturePath('docker/.sail-extended.json')), true);

    expect($compose)
        ->toContain('ghcr.io/eloquage/laravel-sail-eloquage:php-8.5-serve-development')
        ->toContain('pull_policy: missing')
        ->not->toContain('context: ./docker/8.5');

    expect($manifest['imageMode'])->toBe('registry')
        ->and($manifest['dockerPublished'] ?? false)->toBeFalse();
});

it('installs lean dependency sets with local docker build', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'pgsql',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)
        ->toContain('context: ./docker/8.5')
        ->toContain("HAS_POSTGRES: '1'")
        ->not->toContain('ghcr.io/eloquage/laravel-sail-eloquage');
});

it('installs with octane frankenphp dockerfile', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--octane' => true,
        '--octane-server' => 'frankenphp',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)
        ->toContain('dockerfile: Dockerfile.frankenphp')
        ->toContain("OCTANE_BASE_TAG: 'dunglas/frankenphp:php8.5'");
});

it('installs pgsql with pgvector stub', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'pgsql',
        '--php' => '8.5',
        '--pgsql-version' => '18',
        '--pgsql-pgvector' => true,
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)->toContain('pgvector/pgvector:pg18');
});

it('writes sail extended manifest', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'redis',
        '--php' => '8.5',
        '--horizon' => true,
        '--redis-cache' => true,
        '--no-interaction' => true,
    ]);

    $manifest = json_decode(file_get_contents($this->fixturePath('docker/.sail-extended.json')), true);

    expect($manifest)
        ->toHaveKey('runtime')
        ->toHaveKey('redis')
        ->and($manifest['redis']['horizon'])->toBeTrue()
        ->and($manifest['redis']['cache'])->toBeTrue();
});

it('adds mysql replication services to existing compose', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    Artisan::call('sail:add', [
        'services' => 'mysql',
        '--mysql-version' => '8.4',
        '--mysql-replication' => true,
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)
        ->toContain('mysql-replica:')
        ->toContain('mysql:8.4');
});

it('publishes runtime docker files', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    expect(file_exists($this->fixturePath('docker/8.5/Dockerfile')))->toBeTrue()
        ->and(file_exists($this->fixturePath('docker/8.5/start-container')))->toBeTrue();
});

it('configures replication env vars for pgsql', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'pgsql',
        '--php' => '8.5',
        '--pgsql-version' => '18',
        '--pgsql-replication' => true,
        '--no-interaction' => true,
    ]);

    $environment = file_get_contents($this->fixturePath('.env'));

    expect($environment)->toContain('DB_READ_HOST=pgsql-replica');
});

it('configures octane env variable', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--octane' => true,
        '--octane-server' => 'swoole',
        '--no-interaction' => true,
    ]);

    $environment = file_get_contents($this->fixturePath('.env'));

    expect($environment)->toContain('OCTANE_SERVER=swoole');
});
