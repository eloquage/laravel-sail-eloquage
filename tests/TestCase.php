<?php

namespace Eloquage\LaravelSailExtended\Tests;

use Eloquage\LaravelSailExtended\LaravelSailExtendedServiceProvider;
use Laravel\Sail\SailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SailServiceProvider::class,
            LaravelSailExtendedServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('sail-extended', require dirname(__DIR__).'/config/sail-extended.php');
    }

    protected function fixturePath(string $path = ''): string
    {
        $base = __DIR__.'/Fixtures/app';

        return $path === '' ? $base : $base.'/'.$path;
    }

    protected function useFixtureApp(): void
    {
        $this->resetMutableFixtureState();

        $this->app->useEnvironmentPath($this->fixturePath());
        $this->app->setBasePath($this->fixturePath());
    }

    protected function resetMutableFixtureState(): void
    {
        $manifestPath = $this->fixturePath('docker/.sail-extended.json');

        if (! file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return;
        }

        unset($manifest['dockerPublished'], $manifest['imageMode']);

        if ($manifest === []) {
            unlink($manifestPath);

            return;
        }

        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }
}
