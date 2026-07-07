<?php

namespace Eloquage\LaravelSailExtended\Console\Concerns;

use Eloquage\LaravelSailExtended\Data\DevelopmentSupervisorOptions;
use Eloquage\LaravelSailExtended\Data\RedisIntegrationOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Data\ServiceOptions;
use Eloquage\LaravelSailExtended\SailExtended;
use Eloquage\LaravelSailExtended\Services\HorizonScaffolder;
use Eloquage\LaravelSailExtended\Services\ManifestManager;
use Eloquage\LaravelSailExtended\Services\OctaneScaffolder;
use Eloquage\LaravelSailExtended\Services\PackageManagerDetector;
use Eloquage\LaravelSailExtended\Services\RegistryImageResolver;
use Eloquage\LaravelSailExtended\Services\RuntimeResolver;
use Eloquage\LaravelSailExtended\Services\ServiceCatalog;
use Eloquage\LaravelSailExtended\Services\ServiceDependencyResolver;
use Eloquage\LaravelSailExtended\Services\StubResolver;
use Eloquage\LaravelSailExtended\Services\SupervisordScaffolder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithDockerComposeServices
{
    /**
     * @var array<string>
     */
    protected array $composePaths = [
        'compose.yaml',
        'compose.yml',
        'docker-compose.yaml',
        'docker-compose.yml',
    ];

    protected function catalog(): ServiceCatalog
    {
        return $this->laravel->make(ServiceCatalog::class);
    }

    protected function stubResolver(): StubResolver
    {
        return $this->laravel->make(StubResolver::class);
    }

    protected function manifest(): ManifestManager
    {
        return $this->laravel->make(ManifestManager::class);
    }

    protected function runtimeResolver(): RuntimeResolver
    {
        return $this->laravel->make(RuntimeResolver::class);
    }

    protected function serviceDependencyResolver(): ServiceDependencyResolver
    {
        return $this->laravel->make(ServiceDependencyResolver::class);
    }

    protected function supervisordScaffolder(): SupervisordScaffolder
    {
        return $this->laravel->make(SupervisordScaffolder::class);
    }

    protected function packageManagerDetector(): PackageManagerDetector
    {
        return $this->laravel->make(PackageManagerDetector::class);
    }

    protected function registryImageResolver(): RegistryImageResolver
    {
        return $this->laravel->make(RegistryImageResolver::class);
    }

    protected function requiresLocalBuild(ServiceDependencyFlags $dependencyFlags): bool
    {
        return $this->registryImageResolver()->requiresLocalBuild(
            $dependencyFlags,
            $this->manifest()->dockerPublished(),
        );
    }

    /**
     * @return array<string>
     */
    protected function sailServices(): array
    {
        return $this->catalog()->sailServices();
    }

    /**
     * @return array<string>
     */
    protected function defaultServices(): array
    {
        return $this->catalog()->defaultServices();
    }

    protected function phpVersion(): string
    {
        if ($this->hasOption('php') && $this->option('php')) {
            return (string) $this->option('php');
        }

        return config('sail-extended.default_php_version', '8.5');
    }

    protected function gatherRuntimeOptionsInteractively(): RuntimeOptions
    {
        if ($this->option('no-interaction')) {
            return $this->runtimeOptionsFromCli();
        }

        $existing = $this->manifest()->runtime();

        if ($existing !== null && ! $this->option('reconfigure-runtime')) {
            $label = $existing->octane
                ? "Octane ({$existing->octaneServer})"
                : 'artisan serve';

            if (function_exists('\Laravel\Prompts\confirm')) {
                if (\Laravel\Prompts\confirm("Keep current runtime configuration ({$label})?", default: true)) {
                    return $existing;
                }
            } elseif ($this->confirm("Keep current runtime configuration ({$label})?", true)) {
                return $existing;
            }
        }

        $octane = $this->option('octane')
            ? true
            : ($this->option('no-octane') ? false : $this->promptConfirm('Would you like to use Laravel Octane?'));

        if (! $octane) {
            return new RuntimeOptions;
        }

        $servers = $this->catalog()->octaneServersForPhpVersion($this->phpVersion());

        if ($servers === []) {
            $this->components->error('No Octane servers support PHP '.$this->phpVersion().'.');

            return new RuntimeOptions;
        }

        $defaultServer = $this->option('octane-server')
            ?? config('sail-extended.octane.default_server', 'frankenphp');

        if (! in_array($defaultServer, $servers, true)) {
            $defaultServer = $servers[0];
        }

        if (function_exists('\Laravel\Prompts\select')) {
            $server = \Laravel\Prompts\select(
                label: 'Which Octane server would you like to use?',
                options: array_combine($servers, $servers),
                default: $defaultServer,
            );
        } else {
            $server = $this->choice('Which Octane server would you like to use?', $servers, array_search($defaultServer, $servers, true) ?: 0);
        }

        return new RuntimeOptions(octane: true, octaneServer: $server);
    }

    protected function runtimeOptionsFromCli(): RuntimeOptions
    {
        if ($this->option('octane')) {
            $server = $this->option('octane-server') ?? config('sail-extended.octane.default_server', 'frankenphp');

            return new RuntimeOptions(octane: true, octaneServer: $server);
        }

        return new RuntimeOptions;
    }

    /**
     * @param  array<string>  $services
     * @param  array<string>  $newServices
     */
    protected function gatherRedisIntegrationOptions(array $services, array $newServices): RedisIntegrationOptions
    {
        if (! in_array('redis', $newServices, true)) {
            return $this->manifest()->redis() ?? new RedisIntegrationOptions;
        }

        if ($this->option('no-interaction')) {
            return new RedisIntegrationOptions(
                horizon: (bool) $this->option('horizon'),
                cache: (bool) $this->option('redis-cache'),
                session: (bool) $this->option('redis-session'),
            );
        }

        $horizon = $this->option('horizon') || $this->promptConfirm('Install and configure Laravel Horizon as the queue adapter?');
        $cache = $this->option('redis-cache') || $this->promptConfirm('Use Redis as the cache store?');
        $session = $this->option('redis-session') || $this->promptConfirm('Use Redis as the session driver?');

        return new RedisIntegrationOptions(horizon: $horizon, cache: $cache, session: $session);
    }

    protected function gatherDevelopmentSupervisorOptions(
        RuntimeOptions $runtime,
        RedisIntegrationOptions $redisOptions,
    ): DevelopmentSupervisorOptions {
        $defaults = config('sail-extended.supervisor.defaults', []);
        $detector = $this->packageManagerDetector();
        $packageManager = $detector->detect();
        $hasPackageJson = $detector->hasPackageJson();

        if ($this->option('no-interaction')) {
            return new DevelopmentSupervisorOptions(
                schedule: (bool) ($this->option('supervisor-schedule') ?? $defaults['schedule'] ?? false),
                horizon: $redisOptions->horizon && (bool) ($this->option('supervisor-horizon') ?? $defaults['horizon'] ?? false),
                vite: $hasPackageJson && ! $this->option('no-supervisor-vite') && (bool) ($this->option('supervisor-vite') ?? $defaults['vite'] ?? false),
                packageManager: $packageManager,
            );
        }

        $existing = $this->manifest()->supervisor();

        if ($existing !== null && ! $this->option('reconfigure-supervisor')) {
            if (function_exists('\Laravel\Prompts\confirm')) {
                if (\Laravel\Prompts\confirm('Keep current supervisord autostart configuration?', default: true)) {
                    return $existing;
                }
            } elseif ($this->confirm('Keep current supervisord autostart configuration?', true)) {
                return $existing;
            }
        }

        if (function_exists('\Laravel\Prompts\note')) {
            \Laravel\Prompts\note('Web server (locked): '.$runtime->webServerLabel());
        } else {
            $this->components->info('Web server (locked): '.$runtime->webServerLabel());
        }

        $choices = [
            'schedule' => 'artisan schedule:work',
        ];

        if ($redisOptions->horizon) {
            $choices['horizon'] = 'artisan horizon:listen';
        }

        if ($hasPackageJson && $packageManager !== null) {
            $choices['vite'] = $detector->devCommand($packageManager) ?? 'npm run dev';
        }

        $selected = [];

        if (function_exists('\Laravel\Prompts\multiselect')) {
            $selected = \Laravel\Prompts\multiselect(
                label: 'Which processes should supervisord autostart in development?',
                options: $choices,
                default: [],
            );
        } else {
            $labels = $this->choice(
                'Which processes should supervisord autostart in development?',
                array_values($choices),
                null,
                null,
                true,
            );
            $labels = is_array($labels) ? $labels : [$labels];
            $selected = array_keys(array_filter(
                $choices,
                fn (string $label): bool => in_array($label, $labels, true),
            ));
        }

        return new DevelopmentSupervisorOptions(
            schedule: in_array('schedule', $selected, true),
            horizon: in_array('horizon', $selected, true),
            vite: in_array('vite', $selected, true),
            packageManager: $packageManager,
        );
    }

    protected function applyDevelopmentSupervisorConfig(
        RuntimeOptions $runtime,
        DevelopmentSupervisorOptions $supervisorOptions,
        ServiceDependencyFlags $dependencyFlags,
    ): void {
        if (! $this->requiresLocalBuild($dependencyFlags)) {
            return;
        }

        $this->supervisordScaffolder()->apply($runtime, $supervisorOptions, $this->phpVersion());
    }

    /**
     * @param  array<string>  $services
     * @return array<string, ServiceOptions>
     */
    protected function gatherServiceOptionsInteractively(array $services): array
    {
        $options = $this->manifest()->services();

        foreach ($services as $service) {
            if (! $this->catalog()->isConfigurable($service)) {
                continue;
            }

            if (isset($options[$service]) && ! $this->hasServiceCliOptions($service)) {
                continue;
            }

            $version = $this->serviceVersionFromCli($service) ?? $this->promptServiceVersion($service);
            $pgvector = false;
            $replication = false;

            if ($this->catalog()->supportsPgvector($service)) {
                $pgvector = $this->servicePgvectorFromCli($service) ?? $this->promptConfirm('Enable pgvector for PostgreSQL?');
            }

            if ($this->catalog()->supportsReplication($service)) {
                $replication = $this->serviceReplicationFromCli($service) ?? $this->promptConfirm('Enable replication for '.$service.'?');
            }

            $options[$service] = new ServiceOptions(
                service: $service,
                version: $version,
                pgvector: $pgvector,
                replication: $replication,
            );
        }

        return $options;
    }

    protected function hasServiceCliOptions(string $service): bool
    {
        $versionOption = $service.'-version';
        $replicationOption = $service.'-replication';

        if ($this->hasOption($versionOption) && $this->option($versionOption) !== null) {
            return true;
        }

        if ($service === 'pgsql' && $this->hasOption('pgsql-pgvector') && $this->option('pgsql-pgvector')) {
            return true;
        }

        return $this->hasOption($replicationOption) && (bool) $this->option($replicationOption);
    }

    protected function serviceVersionFromCli(string $service): ?string
    {
        $option = $service.'-version';

        if (! $this->hasOption($option)) {
            return null;
        }

        $value = $this->option($option);

        return $value !== null ? (string) $value : null;
    }

    protected function servicePgvectorFromCli(string $service): ?bool
    {
        if ($service !== 'pgsql' || ! $this->hasOption('pgsql-pgvector')) {
            return null;
        }

        if ($this->option('pgsql-pgvector')) {
            return true;
        }

        return null;
    }

    protected function serviceReplicationFromCli(string $service): ?bool
    {
        $option = $service.'-replication';

        if (! $this->hasOption($option)) {
            return null;
        }

        if ($this->option($option)) {
            return true;
        }

        return null;
    }

    protected function promptServiceVersion(string $service): string
    {
        $versions = $this->catalog()->versions($service);
        $default = $this->catalog()->defaultVersion($service);

        if (function_exists('\Laravel\Prompts\select')) {
            return \Laravel\Prompts\select(
                label: "Which {$service} version would you like?",
                options: array_combine($versions, $versions),
                default: $default,
            );
        }

        return $this->choice("Which {$service} version would you like?", $versions, array_search($default, $versions, true) ?: 0);
    }

    protected function promptConfirm(string $question): bool
    {
        if (function_exists('\Laravel\Prompts\confirm')) {
            return \Laravel\Prompts\confirm($question);
        }

        return $this->confirm($question);
    }

    /**
     * @return array<string>
     */
    protected function gatherServicesInteractively(): array
    {
        $services = $this->sailServices();

        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $services,
                default: ['mysql'],
            );
        }

        return $this->choice('Which services would you like to install?', $services, 0, null, true);
    }

    /**
     * @param  array<string>  $services
     * @param  array<string, ServiceOptions>  $serviceOptions
     */
    protected function writeComposeFiles(
        array $services,
        array $serviceOptions,
        RuntimeOptions $runtime,
        DevelopmentSupervisorOptions $supervisorOptions,
        ServiceDependencyFlags $dependencyFlags,
    ): void {
        $phpVersion = $this->phpVersion();
        $this->runtimeResolver()->validate($runtime, $phpVersion);

        $this->buildDockerCompose($services, $serviceOptions, $runtime, $supervisorOptions, $dependencyFlags, 'development');
        $this->buildDockerCompose($services, $serviceOptions, $runtime, $supervisorOptions, $dependencyFlags, 'production');
    }

    /**
     * @param  array<string>  $services
     * @param  array<string, ServiceOptions>  $serviceOptions
     */
    protected function buildDockerCompose(
        array $services,
        array $serviceOptions,
        RuntimeOptions $runtime,
        DevelopmentSupervisorOptions $supervisorOptions,
        ServiceDependencyFlags $dependencyFlags,
        string $environment,
    ): void {
        $composePath = $this->composePath($environment);
        $resolver = $this->stubResolver();
        $phpVersion = $this->phpVersion();
        $buildTarget = $environment === 'production' ? 'production' : 'development';
        $composeStub = $environment === 'production'
            ? $resolver->composeProductionStubPath()
            : $resolver->composeStubPath();
        $requiresLocalBuild = $this->requiresLocalBuild($dependencyFlags);

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse($resolver->renderComposeStub(
                $composeStub,
                $phpVersion,
                $buildTarget,
                $runtime,
                $dependencyFlags,
                $requiresLocalBuild,
            ));

        if (! array_key_exists('laravel.test', $compose['services'] ?? [])) {
            $compose['services']['laravel.test'] = Yaml::parse($resolver->renderComposeStub(
                $composeStub,
                $phpVersion,
                $buildTarget,
                $runtime,
                $dependencyFlags,
                $requiresLocalBuild,
            ))['services']['laravel.test'];
        } else {
            $compose['services']['laravel.test'] = $resolver->configureLaravelTestService(
                $compose['services']['laravel.test'],
                $phpVersion,
                $buildTarget,
                $runtime,
                $dependencyFlags,
                $requiresLocalBuild,
            );
        }

        if ($requiresLocalBuild && in_array('mariadb', $services)) {
            $compose['services']['laravel.test']['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
        }

        if ($environment === 'production') {
            $compose['services']['laravel.test']['command'] = explode(' ', $runtime->webCommand());

            if (isset($compose['services']['laravel.test']['ports'])) {
                $compose['services']['laravel.test']['ports'] = array_values(array_filter(
                    $compose['services']['laravel.test']['ports'],
                    fn (mixed $port): bool => ! is_string($port) || ! str_contains($port, '${VITE_PORT'),
                ));
            }

            $workerServices = $this->supervisordScaffolder()->productionWorkerServices(
                $runtime,
                $supervisorOptions,
                $phpVersion,
                $requiresLocalBuild,
            );

            foreach (['horizon', 'scheduler'] as $worker) {
                unset($compose['services'][$worker]);
            }

            foreach ($workerServices as $name => $definition) {
                $compose['services'][$name] = $definition;
            }
        } else {
            unset($compose['services']['laravel.test']['command']);
            unset($compose['services']['horizon'], $compose['services']['scheduler']);
        }

        if (array_key_exists('laravel.test', $compose['services'])) {
            $compose['services']['laravel.test']['depends_on'] = collect($compose['services']['laravel.test']['depends_on'] ?? [])
                ->merge($this->serviceDependencies($services, $serviceOptions))
                ->unique()
                ->values()
                ->all();
        }

        foreach ($services as $service) {
            if (array_key_exists($service, $compose['services'] ?? [])) {
                continue;
            }

            $options = $serviceOptions[$service] ?? new ServiceOptions(
                service: $service,
                version: $this->catalog()->defaultVersion($service),
            );

            if ($this->catalog()->isConfigurable($service)) {
                $definition = $resolver->loadServiceDefinition($options);
                $compose['services'] = array_merge($compose['services'] ?? [], $definition);
            } else {
                $stubPath = SailExtended::stubPath($service.'.stub');
                if (file_exists($stubPath)) {
                    $parsed = Yaml::parseFile($stubPath);
                    if (is_array($parsed)) {
                        $compose['services'] = array_merge($compose['services'] ?? [], $parsed);
                    }
                }
            }
        }

        $volumeServices = ['mysql', 'pgsql', 'pgsql-replica', 'mariadb', 'mongodb', 'redis', 'redis-replica', 'valkey', 'valkey-replica', 'meilisearch', 'typesense', 'typesense-2', 'minio', 'rustfs', 'rabbitmq'];

        collect($services)
            ->flatMap(fn (string $service) => $this->volumeNamesForService($service, $serviceOptions[$service] ?? null))
            ->filter(fn (string $volume) => ! array_key_exists($volume, $compose['volumes'] ?? []))
            ->each(function (string $volume) use (&$compose) {
                $compose['volumes'][$volume] = ['driver' => 'local'];
            });

        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);
        file_put_contents($composePath, $yaml);
    }

    /**
     * @param  array<string>  $services
     * @param  array<string, ServiceOptions>  $serviceOptions
     * @return array<string>
     */
    protected function serviceDependencies(array $services, array $serviceOptions): array
    {
        $dependencies = $services;

        foreach ($services as $service) {
            $options = $serviceOptions[$service] ?? null;

            if ($options?->replication) {
                $dependencies[] = match ($service) {
                    'pgsql' => 'pgsql-replica',
                    'mysql' => 'mysql-replica',
                    'mariadb' => 'mariadb-replica',
                    'redis' => 'redis-replica',
                    'valkey' => 'valkey-replica',
                    'typesense' => 'typesense-2',
                    default => null,
                };
            }
        }

        return array_values(array_filter(array_unique($dependencies)));
    }

    /**
     * @return list<string>
     */
    protected function volumeNamesForService(string $service, ?ServiceOptions $options): array
    {
        if ($options?->replication) {
            return match ($service) {
                'pgsql' => ['sail-pgsql', 'sail-pgsql-replica'],
                'mysql' => ['sail-mysql', 'sail-mysql-replica'],
                'mariadb' => ['sail-mariadb', 'sail-mariadb-replica'],
                'mongodb' => ['sail-mongodb'],
                'redis' => ['sail-redis', 'sail-redis-replica'],
                'valkey' => ['sail-valkey', 'sail-valkey-replica'],
                'typesense' => ['sail-typesense', 'sail-typesense-2'],
                default => in_array($service, ['meilisearch', 'minio', 'rustfs', 'rabbitmq'], true)
                    ? ['sail-'.$service]
                    : [],
            };
        }

        if (in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio', 'rustfs', 'rabbitmq'], true)) {
            return ['sail-'.$service];
        }

        return [];
    }

    /**
     * @param  array<string>  $services
     * @param  array<string, ServiceOptions>  $serviceOptions
     */
    protected function replaceEnvVariables(array $services, array $serviceOptions, RuntimeOptions $runtime, RedisIntegrationOptions $redisOptions): void
    {
        $path = $this->laravel->basePath('.env');

        if (! file_exists($path)) {
            return;
        }

        $environment = file_get_contents($path);

        if ($environment === false) {
            return;
        }

        if (in_array('mysql', $services) || in_array('mariadb', $services) || in_array('pgsql', $services)) {
            foreach (['# DB_HOST=127.0.0.1', '# DB_PORT=3306', '# DB_DATABASE=laravel', '# DB_USERNAME=root', '# DB_PASSWORD='] as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
        }

        if (in_array('mysql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mysql', $environment);
        } elseif (in_array('pgsql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=pgsql', $environment);
            $environment = str_replace('DB_PORT=3306', 'DB_PORT=5432', $environment);
        } elseif (in_array('mariadb', $services)) {
            if (config()->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            }
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mariadb', $environment);
        }

        $environment = str_replace('DB_USERNAME=root', 'DB_USERNAME=sail', $environment);
        $environment = preg_replace('/DB_PASSWORD=(.*)/', 'DB_PASSWORD=password', $environment);

        foreach ($serviceOptions as $service => $options) {
            if ($options->replication) {
                $environment = match ($service) {
                    'pgsql' => $this->appendEnv($environment, 'DB_READ_HOST', 'pgsql-replica'),
                    'mysql' => $this->appendEnv($environment, 'DB_READ_HOST', 'mysql-replica'),
                    'mariadb' => $this->appendEnv($environment, 'DB_READ_HOST', 'mariadb-replica'),
                    'redis' => $this->appendEnv($environment, 'REDIS_REPLICA_HOST', 'redis-replica'),
                    'valkey' => $this->appendEnv($environment, 'REDIS_REPLICA_HOST', 'valkey-replica'),
                    'mongodb' => preg_replace(
                        '/MONGODB_URI=.*/',
                        'MONGODB_URI=mongodb://mongodb:27017/?replicaSet=rs0',
                        $environment
                    ) ?: $this->appendEnv($environment, 'MONGODB_URI', 'mongodb://mongodb:27017/?replicaSet=rs0'),
                    default => $environment,
                };
            }
        }

        if (in_array('memcached', $services)) {
            $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        }

        if (in_array('redis', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        }

        if (in_array('valkey', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=valkey', $environment);
        }

        if (in_array('mongodb', $services) && ! str_contains($environment, 'MONGODB_URI=')) {
            $environment .= PHP_EOL.'MONGODB_URI=mongodb://mongodb:27017';
            $environment .= PHP_EOL.'MONGODB_DATABASE=laravel';
        }

        if (in_array('meilisearch', $services)) {
            $environment .= PHP_EOL.'SCOUT_DRIVER=meilisearch';
            $environment .= PHP_EOL.'MEILISEARCH_HOST=http://meilisearch:7700';
            $environment .= PHP_EOL.'MEILISEARCH_NO_ANALYTICS=false';
        }

        if (in_array('typesense', $services)) {
            $environment .= PHP_EOL.'SCOUT_DRIVER=typesense';
            $environment .= PHP_EOL.'TYPESENSE_HOST=typesense';
            $environment .= PHP_EOL.'TYPESENSE_PORT=8108';
            $environment .= PHP_EOL.'TYPESENSE_PROTOCOL=http';
            $environment .= PHP_EOL.'TYPESENSE_API_KEY=xyz';
        }

        if (in_array('soketi', $services)) {
            $environment = preg_replace('/^BROADCAST_DRIVER=(.*)/m', 'BROADCAST_DRIVER=pusher', $environment);
            $environment = preg_replace('/^PUSHER_APP_ID=(.*)/m', 'PUSHER_APP_ID=app-id', $environment);
            $environment = preg_replace('/^PUSHER_APP_KEY=(.*)/m', 'PUSHER_APP_KEY=app-key', $environment);
            $environment = preg_replace('/^PUSHER_APP_SECRET=(.*)/m', 'PUSHER_APP_SECRET=app-secret', $environment);
            $environment = preg_replace('/^PUSHER_HOST=(.*)/m', 'PUSHER_HOST=soketi', $environment);
            $environment = preg_replace('/^PUSHER_PORT=(.*)/m', 'PUSHER_PORT=6001', $environment);
            $environment = preg_replace('/^PUSHER_SCHEME=(.*)/m', 'PUSHER_SCHEME=http', $environment);
            $environment = preg_replace('/^VITE_PUSHER_HOST=(.*)/m', 'VITE_PUSHER_HOST=localhost', $environment);
        }

        if (in_array('mailpit', $services)) {
            $environment = preg_replace('/^MAIL_MAILER=(.*)/m', 'MAIL_MAILER=smtp', $environment);
            $environment = preg_replace('/^MAIL_HOST=(.*)/m', 'MAIL_HOST=mailpit', $environment);
            $environment = preg_replace('/^MAIL_PORT=(.*)/m', 'MAIL_PORT=1025', $environment);
        }

        if (in_array('rabbitmq', $services)) {
            $environment = str_replace('RABBITMQ_HOST=127.0.0.1', 'RABBITMQ_HOST=rabbitmq', $environment);
        }

        if ($runtime->octane && $runtime->octaneServer) {
            $environment = $this->appendEnv($environment, 'OCTANE_SERVER', $runtime->octaneServer);
        }

        file_put_contents($path, $environment);

        $this->laravel->make(HorizonScaffolder::class)->configureEnv($redisOptions);
    }

    protected function appendEnv(string $environment, string $key, string $value): string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $environment)) {
            return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $key.'='.$value, $environment);
        }

        return $environment.PHP_EOL.$key.'='.$value.PHP_EOL;
    }

    protected function configurePhpUnit(): void
    {
        $path = $this->laravel->basePath('phpunit.xml');

        if (! file_exists($path)) {
            $path = $this->laravel->basePath('phpunit.xml.dist');

            if (! file_exists($path)) {
                return;
            }
        }

        $phpunit = file_get_contents($path);

        if ($phpunit === false) {
            return;
        }

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace(
            ['<!-- <env name="DB_DATABASE" value=":memory:"/> -->', '<env name="DB_DATABASE" value=":memory:"/>'],
            '<env name="DB_DATABASE" value="testing"/>',
            $phpunit
        );

        file_put_contents($this->laravel->basePath('phpunit.xml'), $phpunit);
    }

    protected function installDevContainer(): void
    {
        if (! is_dir($this->laravel->basePath('.devcontainer'))) {
            mkdir($this->laravel->basePath('.devcontainer'), 0755, true);
        }

        file_put_contents(
            $this->laravel->basePath('.devcontainer/devcontainer.json'),
            file_get_contents(SailExtended::stubPath('devcontainer.stub'))
        );

        $environment = file_get_contents($this->laravel->basePath('.env'));
        $environment .= PHP_EOL.'WWWGROUP=1000';
        $environment .= PHP_EOL.'WWWUSER=1000'.PHP_EOL;
        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    protected function configureRedisIntegrations(RedisIntegrationOptions $options, RuntimeOptions $runtime): void
    {
        //
    }

    protected function configureOctane(RuntimeOptions $runtime): void
    {
        if (! $runtime->octane || $runtime->octaneServer === null) {
            return;
        }

        $octane = $this->laravel->make(OctaneScaffolder::class);
        $octane->configureEnv($runtime->octaneServer);
    }

    protected function installIntegrationsAfterBuild(RuntimeOptions $runtime, RedisIntegrationOptions $redisOptions): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if ($runtime->octane && $runtime->octaneServer !== null) {
            $octane = $this->laravel->make(OctaneScaffolder::class);

            if ($octane->installViaSail($runtime->octaneServer)) {
                $this->components->info('Laravel Octane installed successfully.');
            } else {
                $this->components->warn(
                    'Octane installation failed. Run: ./vendor/bin/sail composer require laravel/octane && ./vendor/bin/sail artisan octane:install --server='.$runtime->octaneServer
                );
            }
        }

        if ($redisOptions->horizon) {
            $horizon = $this->laravel->make(HorizonScaffolder::class);

            if ($horizon->installViaSail()) {
                $this->components->info('Laravel Horizon installed successfully.');
            } else {
                $this->components->warn(
                    'Horizon installation failed. Run: ./vendor/bin/sail composer require laravel/horizon && ./vendor/bin/sail artisan horizon:install'
                );
            }
        }
    }

    protected function showDeferredIntegrationInstructions(RuntimeOptions $runtime, RedisIntegrationOptions $redisOptions): void
    {
        if ($this->option('no-interaction')) {
            return;
        }

        if ($runtime->octane && $runtime->octaneServer !== null) {
            $this->components->warn(
                'Docker build was skipped or failed. After the image builds, run: ./vendor/bin/sail composer require laravel/octane && ./vendor/bin/sail artisan octane:install --server='.$runtime->octaneServer
            );
        }

        if ($redisOptions->horizon) {
            $this->components->warn(
                'Docker build was skipped or failed. After the image builds, run: ./vendor/bin/sail composer require laravel/horizon && ./vendor/bin/sail artisan horizon:install'
            );
        }
    }

    protected function publishRuntimeAssets(RuntimeOptions $runtime, ServiceDependencyFlags $dependencyFlags): void
    {
        if (! $this->requiresLocalBuild($dependencyFlags)) {
            return;
        }

        $this->runtimeResolver()->publishRuntime($this->phpVersion(), $runtime, $dependencyFlags);
    }

    /**
     * @param  array<string>  $services
     */
    protected function prepareInstallation(array $services, ServiceDependencyFlags $dependencyFlags): bool
    {
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return false;
        }

        if (count($services) > 0) {
            if ($this->runCommands(['./vendor/bin/sail pull '.implode(' ', $services)]) !== 0) {
                return false;
            }
        }

        if ($this->requiresLocalBuild($dependencyFlags)) {
            return $this->runCommands(['./vendor/bin/sail build']) === 0;
        }

        return $this->runCommands(['./vendor/bin/sail pull']) === 0;
    }

    /**
     * @param  array<string>  $commands
     */
    protected function runCommands(array $commands): int
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), $this->laravel->basePath(), null, null, null);

        if (DIRECTORY_SEPARATOR !== '\\' && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });
    }

    protected function composePath(string $environment = 'development'): string
    {
        $file = $environment === 'production'
            ? config('sail-extended.compose_files.production', 'compose.yaml.production')
            : config('sail-extended.compose_files.development', 'compose.yaml');

        if ($environment === 'development') {
            $existing = collect($this->composePaths)
                ->map(fn (string $path) => $this->laravel->basePath($path))
                ->first(fn (string $path) => file_exists($path));

            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->laravel->basePath($file);
    }

    /**
     * @param  array<string>  $services
     * @return array<string>
     */
    protected function newServices(array $services): array
    {
        $composePath = $this->composePath();
        $existing = file_exists($composePath) ? Yaml::parseFile($composePath) : ['services' => []];

        return array_values(array_filter($services, fn (string $service) => ! array_key_exists($service, $existing['services'] ?? [])));
    }
}
