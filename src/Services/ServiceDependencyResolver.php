<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;

class ServiceDependencyResolver
{
    /**
     * @param  array<string>  $services
     */
    public function resolve(array $services): ServiceDependencyFlags
    {
        $flags = new ServiceDependencyFlags;

        foreach ($services as $service) {
            $flags = $flags->merge($this->flagsForService($service));
        }

        return $flags;
    }

    protected function flagsForService(string $service): ServiceDependencyFlags
    {
        return match ($service) {
            'mysql', 'mariadb' => new ServiceDependencyFlags(hasMysql: true),
            'pgsql' => new ServiceDependencyFlags(hasPostgres: true),
            'mongodb' => new ServiceDependencyFlags(hasMongo: true),
            'redis', 'valkey' => new ServiceDependencyFlags(hasRedis: true),
            'memcached' => new ServiceDependencyFlags(hasMemcached: true),
            default => new ServiceDependencyFlags,
        };
    }
}
