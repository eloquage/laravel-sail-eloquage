<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Support\SailProcessRunner;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Process;

class OctaneScaffolder
{
    public function __construct(
        protected Application $app,
        protected SailProcessRunner $sail,
    ) {}

    public function install(string $server): bool
    {
        if ($this->runCommand(['composer', 'show', 'laravel/octane']) !== 0) {
            if ($this->runCommand(['composer', 'require', 'laravel/octane', '--no-interaction']) !== 0) {
                return false;
            }
        }

        return $this->runCommand([
            PHP_BINARY,
            'artisan',
            'octane:install',
            '--server='.$server,
            '--no-interaction',
        ]) === 0;
    }

    public function installViaSail(string $server): bool
    {
        if (! $this->sail->isAvailable()) {
            return false;
        }

        if ($this->sail->run(['composer', 'show', 'laravel/octane']) !== 0) {
            if ($this->sail->run(['composer', 'require', 'laravel/octane', '--no-interaction']) !== 0) {
                return false;
            }
        }

        return $this->sail->run([
            'artisan',
            'octane:install',
            '--server='.$server,
            '--no-interaction',
        ]) === 0;
    }

    public function configureEnv(string $server): void
    {
        $path = $this->app->basePath('.env');

        if (! file_exists($path)) {
            return;
        }

        $environment = file_get_contents($path);

        if ($environment === false) {
            return;
        }

        if (preg_match('/^OCTANE_SERVER=.*/m', $environment)) {
            $environment = preg_replace('/^OCTANE_SERVER=.*/m', 'OCTANE_SERVER='.$server, $environment);
        } else {
            $environment .= PHP_EOL.'OCTANE_SERVER='.$server.PHP_EOL;
        }

        file_put_contents($path, $environment);
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
