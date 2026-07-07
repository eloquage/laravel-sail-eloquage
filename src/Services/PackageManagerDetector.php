<?php

namespace Eloquage\LaravelSailExtended\Services;

use Illuminate\Contracts\Foundation\Application;

class PackageManagerDetector
{
    public function __construct(
        protected Application $app,
    ) {}

    public function detect(): ?string
    {
        $base = $this->app->basePath();
        $priority = config('sail-extended.package_managers', ['bun', 'pnpm', 'npm']);

        foreach ($priority as $manager) {
            if ($this->hasLockfile($base, $manager)) {
                return $manager;
            }
        }

        return file_exists($base.'/package.json') ? 'npm' : null;
    }

    public function devCommand(?string $manager = null): ?string
    {
        $manager ??= $this->detect();

        if ($manager === null) {
            return null;
        }

        return match ($manager) {
            'bun' => 'bun run dev',
            'pnpm' => 'pnpm run dev',
            default => 'npm run dev',
        };
    }

    public function hasPackageJson(): bool
    {
        return file_exists($this->app->basePath('package.json'));
    }

    protected function hasLockfile(string $base, string $manager): bool
    {
        return match ($manager) {
            'bun' => file_exists($base.'/bun.lock') || file_exists($base.'/bun.lockb'),
            'pnpm' => file_exists($base.'/pnpm-lock.yaml'),
            'npm' => file_exists($base.'/package-lock.json'),
            default => false,
        };
    }
}
