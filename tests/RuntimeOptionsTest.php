<?php

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;

it('returns default dockerfile for serve mode', function () {
    $runtime = new RuntimeOptions;

    expect($runtime->dockerfileName())->toBe('Dockerfile')
        ->and($runtime->supervisordConfigName())->toBe('supervisord.conf')
        ->and($runtime->baseImageTag('8.5'))->toBeNull();
});

it('returns octane dockerfile for frankenphp', function () {
    $runtime = new RuntimeOptions(octane: true, octaneServer: 'frankenphp');

    expect($runtime->dockerfileName())->toBe('Dockerfile.frankenphp')
        ->and($runtime->supervisordConfigName())->toBe('supervisord.frankenphp.conf')
        ->and($runtime->baseImageTag('8.5'))->toBe('dunglas/frankenphp:php8.5');
});

it('returns octane dockerfile for swoole', function () {
    $runtime = new RuntimeOptions(octane: true, octaneServer: 'swoole');

    expect($runtime->dockerfileName())->toBe('Dockerfile.swoole')
        ->and($runtime->baseImageTag('8.5'))->toBe('phpswoole/swoole:php8.5-alpine');
});

it('serializes runtime options to array', function () {
    $runtime = new RuntimeOptions(octane: true, octaneServer: 'roadrunner');

    expect(RuntimeOptions::fromArray($runtime->toArray()))
        ->octane->toBeTrue()
        ->octaneServer->toBe('roadrunner');
});
