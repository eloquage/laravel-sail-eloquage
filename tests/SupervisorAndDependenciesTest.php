<?php

use Eloquage\LaravelSailExtended\Data\DevelopmentSupervisorOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Services\PackageManagerDetector;
use Eloquage\LaravelSailExtended\Services\RuntimeStubRenderer;
use Eloquage\LaravelSailExtended\Services\ServiceDependencyResolver;
use Eloquage\LaravelSailExtended\Services\SupervisordScaffolder;
use Illuminate\Support\Facades\Artisan;

it('serializes development supervisor options', function () {
    $options = new DevelopmentSupervisorOptions(
        schedule: true,
        horizon: true,
        vite: true,
        packageManager: 'pnpm',
    );

    expect(DevelopmentSupervisorOptions::fromArray($options->toArray()))
        ->schedule->toBeTrue()
        ->horizon->toBeTrue()
        ->vite->toBeTrue()
        ->packageManager->toBe('pnpm');
});

it('resolves service dependency flags from selected services', function () {
    $resolver = app(ServiceDependencyResolver::class);

    $flags = $resolver->resolve(['pgsql', 'redis', 'valkey', 'mariadb', 'memcached', 'mongodb']);

    expect($flags)
        ->hasPostgres->toBeTrue()
        ->hasRedis->toBeTrue()
        ->hasMysql->toBeTrue()
        ->hasMemcached->toBeTrue()
        ->hasMongo->toBeTrue();
});

it('maps dependency flags to compose build args', function () {
    $flags = new ServiceDependencyFlags(hasPostgres: true, hasRedis: true);

    expect($flags->toBuildArgs())->toBe([
        'HAS_MYSQL' => '0',
        'HAS_POSTGRES' => '1',
        'HAS_MONGO' => '0',
        'HAS_REDIS' => '1',
        'HAS_MEMCACHED' => '0',
    ]);
});

it('detects package manager from lockfiles', function () {
    $this->useFixtureApp();

    file_put_contents($this->fixturePath('package.json'), '{}');
    file_put_contents($this->fixturePath('pnpm-lock.yaml'), '');

    expect(app(PackageManagerDetector::class)->detect())->toBe('pnpm')
        ->and(app(PackageManagerDetector::class)->devCommand())->toBe('pnpm run dev');
});

it('renders dockerfile stub without redis packages when not selected', function () {
    $renderer = app(RuntimeStubRenderer::class);
    $dockerfile = $renderer->render(new RuntimeOptions, '8.5');

    expect($dockerfile)
        ->toContain('ARG HAS_REDIS=0')
        ->toContain('if [ "$HAS_MYSQL" = "1" ]')
        ->toContain('if [ "$HAS_POSTGRES" = "1" ]');
});

it('renders dockerfile stub with php version placeholders', function () {
    $renderer = app(RuntimeStubRenderer::class);
    $dockerfile = $renderer->render(new RuntimeOptions(octane: true, octaneServer: 'frankenphp'), '8.5');

    expect($dockerfile)->toContain('dunglas/frankenphp:php8.5');
});

it('applies supervisord fragments for selected development programs', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'none',
        '--php' => '8.5',
        '--no-interaction' => true,
        '--supervisor-schedule' => true,
    ]);

    $supervisord = file_get_contents($this->fixturePath('docker/8.5/supervisord.conf'));

    expect($supervisord)
        ->toContain('[program:php]')
        ->toContain('[program:schedule]')
        ->toContain('schedule:work');
});

it('writes dependency build args to compose when pgsql is selected', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'pgsql',
        '--php' => '8.5',
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml'));

    expect($compose)
        ->toContain("HAS_POSTGRES: '1'")
        ->toContain("HAS_REDIS: '0'");
});

it('writes production worker services when supervisor options are enabled', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'redis',
        '--php' => '8.5',
        '--horizon' => true,
        '--supervisor-schedule' => true,
        '--supervisor-horizon' => true,
        '--no-interaction' => true,
    ]);

    $compose = file_get_contents($this->fixturePath('compose.yaml.production'));

    expect($compose)
        ->toContain('target: production')
        ->toContain('command:')
        ->toContain('- php')
        ->toContain('- artisan')
        ->toContain('- serve')
        ->toContain('horizon:')
        ->toContain('scheduler:')
        ->toContain('horizon:listen')
        ->toContain('schedule:work');
});

it('persists supervisor and dependency manifest keys', function () {
    $this->useFixtureApp();

    Artisan::call('sail:install', [
        '--with' => 'pgsql,redis',
        '--php' => '8.5',
        '--horizon' => true,
        '--supervisor-schedule' => true,
        '--no-interaction' => true,
    ]);

    $manifest = json_decode(file_get_contents($this->fixturePath('docker/.sail-extended.json')), true);

    expect($manifest)
        ->toHaveKey('supervisor')
        ->toHaveKey('dependencies')
        ->and($manifest['supervisor']['schedule'])->toBeTrue()
        ->and($manifest['dependencies']['hasPostgres'])->toBeTrue()
        ->and($manifest['dependencies']['hasRedis'])->toBeTrue();
});

it('merges supervisord config via scaffolder', function () {
    $this->useFixtureApp();

    $runtime = new RuntimeOptions;
    $options = new DevelopmentSupervisorOptions(horizon: true);

    app(SupervisordScaffolder::class)->apply($runtime, $options, '8.5');

    $contents = file_get_contents($this->fixturePath('docker/8.5/supervisord.conf'));

    expect($contents)->toContain('horizon:listen');
});
