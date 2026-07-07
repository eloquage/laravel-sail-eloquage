<?php

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Services\RegistryImageResolver;

it('builds registry image tags from runtime and php version', function () {
    $resolver = app(RegistryImageResolver::class);

    expect($resolver->imageTag(new RuntimeOptions, '8.5', 'development'))
        ->toBe('php-8.5-serve-development')
        ->and($resolver->imageTag(new RuntimeOptions(octane: true, octaneServer: 'frankenphp'), '8.5', 'production'))
        ->toBe('php-8.5-frankenphp-production');
});

it('builds full registry image references', function () {
    $resolver = app(RegistryImageResolver::class);
    $runtime = new RuntimeOptions(octane: true, octaneServer: 'swoole');

    expect($resolver->imageReference($runtime, '8.4', 'development'))
        ->toBe('ghcr.io/eloquage/laravel-sail-extended:php-8.4-swoole-development');
});

it('requires local build when dependency flags are not full stack', function () {
    $resolver = app(RegistryImageResolver::class);

    expect($resolver->requiresLocalBuild(new ServiceDependencyFlags(hasPostgres: true), false))->toBeTrue()
        ->and($resolver->requiresLocalBuild(new ServiceDependencyFlags(
            hasMysql: true,
            hasPostgres: true,
            hasMongo: true,
            hasRedis: true,
            hasMemcached: true,
        ), false))->toBeFalse();
});

it('requires local build after docker assets are published', function () {
    $resolver = app(RegistryImageResolver::class);
    $flags = new ServiceDependencyFlags(
        hasMysql: true,
        hasPostgres: true,
        hasMongo: true,
        hasRedis: true,
        hasMemcached: true,
    );

    expect($resolver->requiresLocalBuild($flags, true))->toBeTrue();
});

it('detects full stack dependency flags', function () {
    expect((new ServiceDependencyFlags)->isFullStack())->toBeFalse()
        ->and((new ServiceDependencyFlags(
            hasMysql: true,
            hasPostgres: true,
            hasMongo: true,
            hasRedis: true,
            hasMemcached: true,
        ))->isFullStack())->toBeTrue();
});
