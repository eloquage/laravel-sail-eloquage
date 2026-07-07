<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\DevelopmentSupervisorOptions;
use Eloquage\LaravelSailExtended\Data\RedisIntegrationOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\Data\ServiceOptions;
use Illuminate\Contracts\Foundation\Application;

class ManifestManager
{
    public function __construct(
        protected Application $app,
    ) {}

    public function path(): string
    {
        return $this->app->basePath(config('sail-extended.manifest_path', 'docker/.sail-extended.json'));
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->path();

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        return json_decode($contents, true) ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function write(array $data): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }

    public function runtime(): ?RuntimeOptions
    {
        $data = $this->read();

        if (! isset($data['runtime'])) {
            return null;
        }

        return RuntimeOptions::fromArray($data['runtime']);
    }

    public function redis(): ?RedisIntegrationOptions
    {
        $data = $this->read();

        if (! isset($data['redis'])) {
            return null;
        }

        return RedisIntegrationOptions::fromArray($data['redis']);
    }

    /**
     * @return array<string, ServiceOptions>
     */
    public function services(): array
    {
        $data = $this->read();
        $services = [];

        foreach ($data['services'] ?? [] as $name => $options) {
            $services[$name] = ServiceOptions::fromArray($name, $options);
        }

        return $services;
    }

    public function mergeRuntime(RuntimeOptions $runtime): void
    {
        $data = $this->read();
        $data['runtime'] = $runtime->toArray();
        $this->write($data);
    }

    public function mergeRedis(RedisIntegrationOptions $redis): void
    {
        $data = $this->read();
        $data['redis'] = $redis->toArray();
        $this->write($data);
    }

    public function supervisor(): ?DevelopmentSupervisorOptions
    {
        $data = $this->read();

        if (! isset($data['supervisor'])) {
            return null;
        }

        return DevelopmentSupervisorOptions::fromArray($data['supervisor']);
    }

    public function dependencies(): ?ServiceDependencyFlags
    {
        $data = $this->read();

        if (! isset($data['dependencies'])) {
            return null;
        }

        return ServiceDependencyFlags::fromArray($data['dependencies']);
    }

    public function mergeSupervisor(DevelopmentSupervisorOptions $supervisor): void
    {
        $data = $this->read();
        $data['supervisor'] = $supervisor->toArray();
        $this->write($data);
    }

    public function mergeDependencies(ServiceDependencyFlags $dependencies): void
    {
        $data = $this->read();
        $existing = isset($data['dependencies'])
            ? ServiceDependencyFlags::fromArray($data['dependencies'])
            : new ServiceDependencyFlags;

        $data['dependencies'] = $existing->merge($dependencies)->toArray();
        $this->write($data);
    }

    /**
     * @param  array<string, ServiceOptions>  $services
     */
    public function mergeServices(array $services): void
    {
        $data = $this->read();
        $existing = $data['services'] ?? [];

        foreach ($services as $name => $options) {
            $existing[$name] = $options->toArray();
        }

        $data['services'] = $existing;
        $this->write($data);
    }

    public function dockerPublished(): bool
    {
        return (bool) ($this->read()['dockerPublished'] ?? false);
    }

    public function imageMode(): string
    {
        return (string) ($this->read()['imageMode'] ?? 'registry');
    }

    public function markDockerPublished(): void
    {
        $data = $this->read();
        $data['dockerPublished'] = true;
        $data['imageMode'] = 'local';
        $this->write($data);
    }

    public function mergeImageMode(string $mode): void
    {
        $data = $this->read();
        $data['imageMode'] = $mode;

        if ($mode === 'registry') {
            $data['dockerPublished'] = false;
        }

        $this->write($data);
    }
}
