<?php

namespace Eloquage\LaravelSailExtended\Data;

final readonly class ServiceDependencyFlags
{
    public function __construct(
        public bool $hasMysql = false,
        public bool $hasPostgres = false,
        public bool $hasMongo = false,
        public bool $hasRedis = false,
        public bool $hasMemcached = false,
    ) {}

    public function isFullStack(): bool
    {
        return $this->hasMysql
            && $this->hasPostgres
            && $this->hasMongo
            && $this->hasRedis
            && $this->hasMemcached;
    }

    /**
     * @return array<string, string>
     */
    public function toBuildArgs(): array
    {
        return [
            'HAS_MYSQL' => $this->hasMysql ? '1' : '0',
            'HAS_POSTGRES' => $this->hasPostgres ? '1' : '0',
            'HAS_MONGO' => $this->hasMongo ? '1' : '0',
            'HAS_REDIS' => $this->hasRedis ? '1' : '0',
            'HAS_MEMCACHED' => $this->hasMemcached ? '1' : '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hasMysql' => $this->hasMysql,
            'hasPostgres' => $this->hasPostgres,
            'hasMongo' => $this->hasMongo,
            'hasRedis' => $this->hasRedis,
            'hasMemcached' => $this->hasMemcached,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hasMysql: (bool) ($data['hasMysql'] ?? false),
            hasPostgres: (bool) ($data['hasPostgres'] ?? false),
            hasMongo: (bool) ($data['hasMongo'] ?? false),
            hasRedis: (bool) ($data['hasRedis'] ?? false),
            hasMemcached: (bool) ($data['hasMemcached'] ?? false),
        );
    }

    public function merge(self $other): self
    {
        return new self(
            hasMysql: $this->hasMysql || $other->hasMysql,
            hasPostgres: $this->hasPostgres || $other->hasPostgres,
            hasMongo: $this->hasMongo || $other->hasMongo,
            hasRedis: $this->hasRedis || $other->hasRedis,
            hasMemcached: $this->hasMemcached || $other->hasMemcached,
        );
    }
}
