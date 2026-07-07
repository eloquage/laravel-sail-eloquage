<?php

namespace Eloquage\LaravelSailExtended\Console;

use Eloquage\LaravelSailExtended\Console\Concerns\InteractsWithDockerComposeServices;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:add')]
class AddCommand extends Command
{
    use InteractsWithDockerComposeServices;

    protected $signature = 'sail:add
        {services? : The services that should be added}
        {--php=8.5 : The PHP version that should be used}
        {--octane : Enable Laravel Octane}
        {--no-octane : Disable Laravel Octane}
        {--octane-server= : The Octane server (frankenphp, swoole, roadrunner)}
        {--reconfigure-runtime : Reconfigure the runtime options}
        {--reconfigure-supervisor : Reconfigure supervisord autostart options}
        {--horizon : Install Laravel Horizon}
        {--redis-cache : Use Redis for cache}
        {--redis-session : Use Redis for sessions}
        {--supervisor-schedule : Autostart schedule:work in development supervisord}
        {--supervisor-horizon : Autostart horizon:listen in development supervisord}
        {--supervisor-vite : Autostart frontend dev server in development supervisord}
        {--no-supervisor-vite : Do not autostart frontend dev server in development supervisord}
        {--pgsql-version= : PostgreSQL version}
        {--pgsql-pgvector : Enable pgvector for PostgreSQL}
        {--pgsql-replication : Enable PostgreSQL replication}
        {--mysql-version= : MySQL version}
        {--mysql-replication : Enable MySQL replication}
        {--mariadb-version= : MariaDB version}
        {--mariadb-replication : Enable MariaDB replication}
        {--mongodb-version= : MongoDB version}
        {--mongodb-replication : Enable MongoDB replication}
        {--redis-version= : Redis version}
        {--redis-replication : Enable Redis replication}
        {--valkey-version= : Valkey version}
        {--valkey-replication : Enable Valkey replication}
        {--meilisearch-version= : Meilisearch version}
        {--typesense-version= : Typesense version}
        {--typesense-replication : Enable Typesense clustering}';

    protected $description = 'Add a service to an existing Sail Extended installation';

    public function handle(): int
    {
        $runtime = $this->gatherRuntimeOptionsInteractively();

        if ($this->argument('services')) {
            $services = $this->argument('services') === 'none' ? [] : explode(',', $this->argument('services'));
        } elseif ($this->option('no-interaction')) {
            $services = $this->defaultServices();
        } else {
            $services = $this->gatherServicesInteractively();
        }

        if ($invalidServices = array_diff($services, $this->sailServices())) {
            $this->components->error('Invalid services ['.implode(',', $invalidServices).'].');

            return self::FAILURE;
        }

        $newServices = $this->newServices($services);
        $serviceOptions = $this->gatherServiceOptionsInteractively($services);
        $existingDependencies = $this->manifest()->dependencies() ?? new ServiceDependencyFlags;
        $dependencyFlags = $existingDependencies->merge($this->serviceDependencyResolver()->resolve($services));
        $redisOptions = $this->gatherRedisIntegrationOptions($services, $newServices);
        $supervisorOptions = $this->gatherDevelopmentSupervisorOptions($runtime, $redisOptions);

        $this->publishRuntimeAssets($runtime, $dependencyFlags);
        $this->applyDevelopmentSupervisorConfig($runtime, $supervisorOptions, $dependencyFlags);
        $this->writeComposeFiles($services, $serviceOptions, $runtime, $supervisorOptions, $dependencyFlags);
        $this->replaceEnvVariables($services, $serviceOptions, $runtime, $redisOptions);
        $this->configurePhpUnit();

        $this->manifest()->mergeRuntime($runtime);
        $this->manifest()->mergeServices($serviceOptions);
        $this->manifest()->mergeRedis($redisOptions);
        $this->manifest()->mergeSupervisor($supervisorOptions);
        $this->manifest()->mergeDependencies($dependencyFlags);
        $this->manifest()->mergeImageMode($this->requiresLocalBuild($dependencyFlags) ? 'local' : 'registry');

        $this->configureOctane($runtime);
        $this->configureRedisIntegrations($redisOptions, $runtime);

        $buildSucceeded = $this->prepareInstallation($newServices, $dependencyFlags);

        if ($buildSucceeded) {
            $this->installIntegrationsAfterBuild($runtime, $redisOptions);
        } else {
            $this->showDeferredIntegrationInstructions($runtime, $redisOptions);
        }

        $this->newLine();
        $this->components->info('Additional Sail Extended services installed successfully.');

        return self::SUCCESS;
    }
}
