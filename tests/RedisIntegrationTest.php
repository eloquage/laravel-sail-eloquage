<?php

use Eloquage\LaravelSailExtended\Data\RedisIntegrationOptions;
use Eloquage\LaravelSailExtended\Services\HorizonScaffolder;

it('patches env for horizon cache and session', function () {
    $this->useFixtureApp();

    $fixtureEnv = $this->fixturePath('.env');
    copy($fixtureEnv, $this->fixturePath('.env.test'));
    rename($this->fixturePath('.env.test'), $fixtureEnv);

    app(HorizonScaffolder::class)->configureEnv(new RedisIntegrationOptions(
        horizon: true,
        cache: true,
        session: true,
    ));

    $environment = file_get_contents($fixtureEnv);

    expect($environment)
        ->toContain('QUEUE_CONNECTION=redis')
        ->toContain('CACHE_STORE=redis')
        ->toContain('SESSION_DRIVER=redis')
        ->toContain('REDIS_HOST=redis');
});
