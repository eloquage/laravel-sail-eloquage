<?php

namespace Eloquage\LaravelSailExtended\Data;

final readonly class ServiceOptions
{
    public function __construct(
        public string $service,
        public string $version,
        public bool $pgvector = false,
        public bool $replication = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'pgvector' => $this->pgvector,
            'replication' => $this->replication,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(string $service, array $data): self
    {
        return new self(
            service: $service,
            version: (string) ($data['version'] ?? config("sail-extended.services.{$service}.default_version", 'latest')),
            pgvector: (bool) ($data['pgvector'] ?? false),
            replication: (bool) ($data['replication'] ?? false),
        );
    }
}
