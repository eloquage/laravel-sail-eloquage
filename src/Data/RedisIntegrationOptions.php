<?php

namespace Eloquage\LaravelSailExtended\Data;

final readonly class RedisIntegrationOptions
{
    public function __construct(
        public bool $horizon = false,
        public bool $cache = false,
        public bool $session = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'horizon' => $this->horizon,
            'cache' => $this->cache,
            'session' => $this->session,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            horizon: (bool) ($data['horizon'] ?? false),
            cache: (bool) ($data['cache'] ?? false),
            session: (bool) ($data['session'] ?? false),
        );
    }
}
