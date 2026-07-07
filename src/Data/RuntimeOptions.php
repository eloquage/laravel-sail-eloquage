<?php

namespace Eloquage\LaravelSailExtended\Data;

final readonly class RuntimeOptions
{
    public function __construct(
        public bool $octane = false,
        public ?string $octaneServer = null,
    ) {}

    public function runtimeSlug(): string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return 'serve';
        }

        return $this->octaneServer;
    }

    public function dockerfileName(): string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return 'Dockerfile';
        }

        return 'Dockerfile.'.$this->octaneServer;
    }

    public function supervisordConfigName(): string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return 'supervisord.conf';
        }

        return 'supervisord.'.$this->octaneServer.'.conf';
    }

    public function baseImageTag(string $phpVersion): ?string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return null;
        }

        return config('sail-extended.octane.images')[$this->octaneServer][$phpVersion] ?? null;
    }

    public function webServerLabel(): string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return 'artisan serve';
        }

        return ucfirst($this->octaneServer).' (Octane)';
    }

    public function webCommand(): string
    {
        if (! $this->octane || $this->octaneServer === null) {
            return 'php artisan serve --host=0.0.0.0 --port=80';
        }

        return match ($this->octaneServer) {
            'frankenphp' => 'php artisan octane:frankenphp --host=0.0.0.0 --port=80',
            'swoole' => 'php artisan octane:start --server=swoole --host=0.0.0.0 --port=80',
            'roadrunner' => 'php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=80',
            default => 'php artisan serve --host=0.0.0.0 --port=80',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'octane' => $this->octane,
            'octaneServer' => $this->octaneServer,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            octane: (bool) ($data['octane'] ?? false),
            octaneServer: $data['octaneServer'] ?? null,
        );
    }
}
