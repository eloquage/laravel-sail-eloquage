<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\RedisIntegrationOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\SailExtended;
use Eloquage\LaravelSailExtended\Support\SailProcessRunner;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Process;

class HorizonScaffolder
{
    public function __construct(
        protected Application $app,
        protected SailProcessRunner $sail,
    ) {}

    public function install(): bool
    {
        if ($this->runCommand(['composer', 'show', 'laravel/horizon']) !== 0) {
            if ($this->runCommand(['composer', 'require', 'laravel/horizon', '--no-interaction']) !== 0) {
                return false;
            }
        }

        return $this->runCommand([
            PHP_BINARY,
            'artisan',
            'horizon:install',
            '--no-interaction',
        ]) === 0;
    }

    public function installViaSail(): bool
    {
        if (! $this->sail->isAvailable()) {
            return false;
        }

        if ($this->sail->run(['composer', 'show', 'laravel/horizon']) !== 0) {
            if ($this->sail->run(['composer', 'require', 'laravel/horizon', '--no-interaction']) !== 0) {
                return false;
            }
        }

        return $this->sail->run([
            'artisan',
            'horizon:install',
            '--no-interaction',
        ]) === 0;
    }

    public function configureEnv(RedisIntegrationOptions $options): void
    {
        $path = $this->app->basePath('.env');

        if (! file_exists($path)) {
            return;
        }

        $environment = file_get_contents($path);

        if ($environment === false) {
            return;
        }

        if ($options->horizon) {
            $environment = $this->setEnvValue($environment, 'QUEUE_CONNECTION', 'redis');
        }

        if ($options->cache) {
            $environment = $this->setEnvValue($environment, 'CACHE_STORE', 'redis');
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        }

        if ($options->session) {
            $environment = $this->setEnvValue($environment, 'SESSION_DRIVER', 'redis');
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        }

        file_put_contents($path, $environment);
    }

    public function mergeHorizonSupervisord(RuntimeOptions $runtime, string $phpVersion): void
    {
        $dockerDir = $this->app->basePath('docker/'.$phpVersion);
        $supervisordFile = $dockerDir.'/'.$runtime->supervisordConfigName();

        if (! file_exists($supervisordFile)) {
            $supervisordFile = $dockerDir.'/supervisord.conf';
        }

        if (! file_exists($supervisordFile)) {
            return;
        }

        $horizonFragment = file_get_contents(SailExtended::runtimePath($phpVersion, 'supervisord.horizon.conf'));

        if ($horizonFragment === false) {
            return;
        }

        $contents = file_get_contents($supervisordFile);

        if ($contents === false || str_contains($contents, '[program:horizon]')) {
            return;
        }

        file_put_contents($supervisordFile, rtrim($contents).PHP_EOL.PHP_EOL.$horizonFragment);
    }

    protected function setEnvValue(string $environment, string $key, string $value): string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $environment)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $key.'='.$value, $environment);
        }

        return $environment.PHP_EOL.$key.'='.$value.PHP_EOL;
    }

    /**
     * @param  list<string>  $command
     */
    protected function runCommand(array $command): int
    {
        $process = new Process($command, $this->app->basePath());
        $process->setTimeout(300);

        return $process->run();
    }
}
