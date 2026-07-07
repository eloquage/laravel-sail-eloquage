<?php

namespace Eloquage\LaravelSailExtended\Services;

class ServiceCatalog
{
    /**
     * @return list<string>
     */
    public function sailServices(): array
    {
        return config('sail-extended.sail_services', []);
    }

    /**
     * @return list<string>
     */
    public function defaultServices(): array
    {
        return config('sail-extended.default_services', []);
    }

    public function isConfigurable(string $service): bool
    {
        return array_key_exists($service, config('sail-extended.services', []));
    }

    /**
     * @return list<string>
     */
    public function versions(string $service): array
    {
        return config("sail-extended.services.{$service}.versions", []);
    }

    public function defaultVersion(string $service): string
    {
        return (string) config("sail-extended.services.{$service}.default_version", 'latest');
    }

    public function supportsPgvector(string $service): bool
    {
        return (bool) config("sail-extended.services.{$service}.pgvector", false);
    }

    public function supportsReplication(string $service): bool
    {
        return (bool) config("sail-extended.services.{$service}.replication", false);
    }

    /**
     * @return list<string>
     */
    public function octaneServersForPhpVersion(string $phpVersion): array
    {
        $servers = [];

        foreach (config('sail-extended.octane.servers', []) as $server) {
            if (isset(config('sail-extended.octane.images')[$server][$phpVersion])) {
                $servers[] = $server;
            }
        }

        return $servers;
    }

    public function isValidOctaneServer(string $server, string $phpVersion): bool
    {
        return in_array($server, $this->octaneServersForPhpVersion($phpVersion), true);
    }
}
