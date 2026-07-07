<?php

namespace Eloquage\LaravelSailExtended\Console;

use Eloquage\LaravelSailExtended\Console\Concerns\InteractsWithDockerComposeServices;
use Eloquage\LaravelSailExtended\Data\DevelopmentSupervisorOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Services\ManifestManager;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:publish')]
class PublishCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'sail:publish';

    protected $description = 'Publish the Laravel Sail Extended Docker files';

    public function handle(): int
    {
        if ($this->getApplication()->has('vendor:publish')) {
            $this->call('vendor:publish', ['--tag' => 'sail-extended-docker']);
            $this->call('vendor:publish', ['--tag' => 'sail-extended-database']);
        }

        $manifest = $this->laravel->make(ManifestManager::class);
        $runtime = $manifest->runtime() ?? new RuntimeOptions;
        $dependencyFlags = $manifest->dependencies() ?? new ServiceDependencyFlags;
        $supervisorOptions = $manifest->supervisor() ?? new DevelopmentSupervisorOptions;
        $serviceOptions = $manifest->services();
        $services = array_keys($serviceOptions);

        foreach (config('sail-extended.compose_files', ['compose.yaml', 'compose.yaml.production']) as $composeFile) {
            $composePath = $this->laravel->basePath($composeFile);

            if (! file_exists($composePath)) {
                continue;
            }

            file_put_contents(
                $composePath,
                str_replace(
                    [
                        './vendor/laravel/sail/runtimes/8.5',
                        './vendor/laravel/sail/runtimes/8.4',
                        './vendor/laravel/sail/runtimes/8.3',
                        './vendor/laravel/sail/runtimes/8.2',
                        './vendor/laravel/sail/runtimes/8.1',
                        './vendor/laravel/sail/runtimes/8.0',
                        './vendor/laravel/sail/database/mariadb',
                        './vendor/laravel/sail/database/mysql',
                        './vendor/laravel/sail/database/pgsql',
                    ],
                    [
                        './docker/8.5',
                        './docker/8.4',
                        './docker/8.3',
                        './docker/8.2',
                        './docker/8.1',
                        './docker/8.0',
                        './docker/mariadb',
                        './docker/mysql',
                        './docker/pgsql',
                    ],
                    file_get_contents($composePath)
                )
            );
        }

        $manifest->markDockerPublished();
        $this->publishRuntimeAssets($runtime, $dependencyFlags);
        $this->writeComposeFiles($services, $serviceOptions, $runtime, $supervisorOptions, $dependencyFlags);

        $this->components->info('Sail Extended Docker files published successfully.');

        return self::SUCCESS;
    }
}
