<?php

namespace Eloquage\LaravelSailExtended\Data;

final readonly class DevelopmentSupervisorOptions
{
    public function __construct(
        public bool $schedule = false,
        public bool $horizon = false,
        public bool $vite = false,
        public ?string $packageManager = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schedule' => $this->schedule,
            'horizon' => $this->horizon,
            'vite' => $this->vite,
            'packageManager' => $this->packageManager,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schedule: (bool) ($data['schedule'] ?? false),
            horizon: (bool) ($data['horizon'] ?? false),
            vite: (bool) ($data['vite'] ?? false),
            packageManager: isset($data['packageManager']) ? (string) $data['packageManager'] : null,
        );
    }
}
