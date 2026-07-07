<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;

class RegistryImageResolver
{
    public function imageTag(RuntimeOptions $runtime, string $phpVersion, string $target): string
    {
        $prefix = (string) config('sail-extended.images.tag_prefix', 'php');

        return "{$prefix}-{$phpVersion}-{$runtime->runtimeSlug()}-{$target}";
    }

    public function imageReference(RuntimeOptions $runtime, string $phpVersion, string $target): string
    {
        $registry = (string) config('sail-extended.images.registry', 'ghcr.io/eloquage/laravel-sail-extended');

        return $registry.':'.$this->imageTag($runtime, $phpVersion, $target);
    }

    public function localImageName(string $phpVersion): string
    {
        return 'sail-'.$phpVersion.'/app';
    }

    public function requiresLocalBuild(ServiceDependencyFlags $flags, bool $dockerPublished): bool
    {
        if ($dockerPublished) {
            return true;
        }

        if (! config('sail-extended.images.full_stack_dependencies', true)) {
            return false;
        }

        return ! $flags->isFullStack();
    }
}
