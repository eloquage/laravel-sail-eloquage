<?php

use Eloquage\LaravelSailExtended\Data\ServiceOptions;
use Eloquage\LaravelSailExtended\Services\ServiceCatalog;
use Eloquage\LaravelSailExtended\Services\StubResolver;

it('resolves pgsql pgvector stub', function () {
    $resolver = app(StubResolver::class);
    $options = new ServiceOptions(service: 'pgsql', version: '18', pgvector: true);

    expect($resolver->resolveServiceStubName($options))->toBe('pgsql-pgvector.stub');
});

it('resolves pgsql replication stub', function () {
    $resolver = app(StubResolver::class);
    $options = new ServiceOptions(service: 'pgsql', version: '18', replication: true);

    expect($resolver->resolveServiceStubName($options))->toBe('pgsql-replication.stub');
});

it('resolves pgsql pgvector replication stub', function () {
    $resolver = app(StubResolver::class);
    $options = new ServiceOptions(service: 'pgsql', version: '18', pgvector: true, replication: true);

    expect($resolver->resolveServiceStubName($options))->toBe('pgsql-pgvector-replication.stub');
});

it('resolves redis replication stub', function () {
    $resolver = app(StubResolver::class);
    $options = new ServiceOptions(service: 'redis', version: '8', replication: true);

    expect($resolver->resolveServiceStubName($options))->toBe('redis-replication.stub');
});

it('loads service definition with version placeholder replaced', function () {
    $resolver = app(StubResolver::class);
    $options = new ServiceOptions(service: 'redis', version: '8');
    $definition = $resolver->loadServiceDefinition($options);

    expect($definition)->toHaveKey('redis')
        ->and($definition['redis']['image'])->toBe('redis:8-alpine');
});

it('lists octane servers for php version', function () {
    $catalog = app(ServiceCatalog::class);

    expect($catalog->octaneServersForPhpVersion('8.5'))
        ->toContain('frankenphp', 'swoole', 'roadrunner');
});
