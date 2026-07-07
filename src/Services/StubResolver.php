<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Data\ServiceOptions;
use Eloquage\LaravelSailExtended\SailExtended;
use Symfony\Component\Yaml\Yaml;

class StubResolver
{
    public function __construct(
        protected ServiceCatalog $catalog,
        protected RegistryImageResolver $registryImageResolver,
    ) {}

    public function resolveServiceStubName(ServiceOptions $options): string
    {
        if (! $this->catalog->isConfigurable($options->service)) {
            return $options->service.'.stub';
        }

        return match ($options->service) {
            'pgsql' => $this->resolvePgsqlStub($options),
            'mysql' => $options->replication ? 'mysql-replication.stub' : 'mysql.stub',
            'mariadb' => $options->replication ? 'mariadb-replication.stub' : 'mariadb.stub',
            'mongodb' => $options->replication ? 'mongodb-replication.stub' : 'mongodb.stub',
            'redis' => $options->replication ? 'redis-replication.stub' : 'redis.stub',
            'valkey' => $options->replication ? 'valkey-replication.stub' : 'valkey.stub',
            'meilisearch' => 'meilisearch.stub',
            'typesense' => $options->replication ? 'typesense-replication.stub' : 'typesense.stub',
            default => $options->service.'.stub',
        };
    }

    protected function resolvePgsqlStub(ServiceOptions $options): string
    {
        if ($options->pgvector && $options->replication) {
            return 'pgsql-pgvector-replication.stub';
        }

        if ($options->pgvector) {
            return 'pgsql-pgvector.stub';
        }

        if ($options->replication) {
            return 'pgsql-replication.stub';
        }

        return 'pgsql.stub';
    }

    /**
     * @return array<string, mixed>
     */
    public function loadServiceDefinition(ServiceOptions $options): array
    {
        $stubName = $this->resolveServiceStubName($options);
        $path = SailExtended::stubPath($stubName);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Stub not found: {$stubName}");
        }

        $contents = $this->replacePlaceholders($contents, $options);

        $parsed = Yaml::parse($contents);

        if (! is_array($parsed)) {
            throw new \RuntimeException("Invalid stub YAML: {$stubName}");
        }

        return $parsed;
    }

    protected function replacePlaceholders(string $contents, ServiceOptions $options): string
    {
        $replacements = [
            '{{VERSION}}' => $options->version,
            '{{PGVECTOR_VERSION}}' => $options->version,
        ];

        if ($options->service === 'meilisearch' && $options->version === 'latest') {
            $replacements['{{VERSION}}'] = 'latest';
        }

        return str_replace(array_keys($replacements), array_values($replacements), $contents);
    }

    public function composeStubPath(): string
    {
        return SailExtended::stubPath('compose.stub');
    }

    public function composeProductionStubPath(): string
    {
        return SailExtended::stubPath('compose.production.stub');
    }

    public function renderComposeStub(
        string $stubPath,
        string $phpVersion,
        string $buildTarget,
        RuntimeOptions $runtime,
        ?ServiceDependencyFlags $dependencies = null,
        bool $requiresLocalBuild = true,
    ): string {
        $contents = file_get_contents($stubPath);

        if ($contents === false) {
            throw new \RuntimeException("Compose stub not found: {$stubPath}");
        }

        $dependencies ??= new ServiceDependencyFlags;

        return str_replace(
            '{{LARAVEL_TEST_IMAGE_BLOCK}}',
            $this->laravelTestImageBlock($phpVersion, $buildTarget, $runtime, $dependencies, $requiresLocalBuild),
            $contents,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function configureLaravelTestService(
        array $service,
        string $phpVersion,
        string $buildTarget,
        RuntimeOptions $runtime,
        ServiceDependencyFlags $dependencies,
        bool $requiresLocalBuild,
    ): array {
        unset($service['pull_policy']);

        if ($requiresLocalBuild) {
            $service['image'] = $this->registryImageResolver->localImageName($phpVersion);
            $service['build'] = $this->localBuildDefinition($phpVersion, $buildTarget, $runtime, $dependencies);

            return $service;
        }

        unset($service['build']);
        $service['image'] = $this->registryImageResolver->imageReference($runtime, $phpVersion, $buildTarget);
        $service['pull_policy'] = 'missing';

        return $service;
    }

    public function laravelTestImageBlock(
        string $phpVersion,
        string $buildTarget,
        RuntimeOptions $runtime,
        ServiceDependencyFlags $dependencies,
        bool $requiresLocalBuild,
    ): string {
        if ($requiresLocalBuild) {
            $lines = [
                '        build:',
                '            context: ./docker/'.$phpVersion,
                '            dockerfile: '.$runtime->dockerfileName(),
                '            target: '.$buildTarget,
                '            args:',
                "                WWWGROUP: '\${WWWGROUP}'",
            ];

            foreach ($dependencies->toBuildArgs() as $key => $value) {
                $lines[] = "                {$key}: '{$value}'";
            }

            $octaneLine = $this->octaneBuildArgsLine($runtime, $phpVersion);

            if ($octaneLine !== '') {
                $lines[] = $octaneLine;
            }

            $lines[] = '        image: '.$this->registryImageResolver->localImageName($phpVersion);

            return implode(PHP_EOL, $lines);
        }

        return implode(PHP_EOL, [
            '        image: '.$this->registryImageResolver->imageReference($runtime, $phpVersion, $buildTarget),
            '        pull_policy: missing',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function localBuildDefinition(
        string $phpVersion,
        string $buildTarget,
        RuntimeOptions $runtime,
        ServiceDependencyFlags $dependencies,
    ): array {
        $args = array_merge(
            ['WWWGROUP' => '${WWWGROUP}'],
            $dependencies->toBuildArgs(),
        );

        if ($runtime->octane) {
            $baseImageTag = $runtime->baseImageTag($phpVersion);

            if ($baseImageTag !== null) {
                $args['OCTANE_BASE_TAG'] = $baseImageTag;
            }
        }

        return [
            'context' => './docker/'.$phpVersion,
            'dockerfile' => $runtime->dockerfileName(),
            'target' => $buildTarget,
            'args' => $args,
        ];
    }

    protected function dependencyBuildArgsLine(ServiceDependencyFlags $dependencies): string
    {
        $lines = [];

        foreach ($dependencies->toBuildArgs() as $key => $value) {
            $lines[] = "                {$key}: '{$value}'";
        }

        return implode(PHP_EOL, $lines);
    }

    protected function octaneBuildArgsLine(RuntimeOptions $runtime, string $phpVersion): string
    {
        if (! $runtime->octane || $runtime->octaneServer === null) {
            return '';
        }

        $baseImageTag = $runtime->baseImageTag($phpVersion);

        if ($baseImageTag === null) {
            return '';
        }

        return "                OCTANE_BASE_TAG: '{$baseImageTag}'";
    }
}
