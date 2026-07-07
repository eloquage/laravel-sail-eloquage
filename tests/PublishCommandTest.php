<?php

use Illuminate\Support\Facades\Artisan;

it('switches compose to local build after publish', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'mysql,pgsql,mongodb,redis,memcached',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)
        ->toContain('ghcr.io/eloquage/laravel-sail-extended')
        ->toContain('pull_policy: missing')
        ->not->toContain('context: ./docker/8.5');

    Artisan::call('sail:publish');

    $compose = file_get_contents($this->fixturePath('compose.yaml'));
    $manifest = json_decode(file_get_contents($this->fixturePath('docker/.sail-extended.json')), true);

    expect($compose)
        ->toContain('context: ./docker/8.5')
        ->toContain('image: sail-8.5/app')
        ->not->toContain('pull_policy: missing');

    expect($manifest)
        ->and($manifest['dockerPublished'])->toBeTrue()
        ->and($manifest['imageMode'])->toBe('local');

    expect(file_exists($this->fixturePath('docker/8.5/Dockerfile')))->toBeTrue();
});
