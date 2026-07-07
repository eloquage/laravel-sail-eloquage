<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Data\ServiceDependencyFlags;
use Eloquage\LaravelSailExtended\SailExtended;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\File;

class RuntimeResolver
{
    public function __construct(
        protected Application $app,
        protected ServiceCatalog $catalog,
        protected RuntimeStubRenderer $stubRenderer,
    ) {}

    public function dockerfileName(RuntimeOptions $runtime): string
    {
        return $runtime->dockerfileName();
    }

    public function baseImageTag(RuntimeOptions $runtime, string $phpVersion): ?string
    {
        if (! $runtime->octane || $runtime->octaneServer === null) {
            return null;
        }

        $tag = $runtime->baseImageTag($phpVersion);

        if ($tag === null) {
            throw new \InvalidArgumentException(
                "Octane server [{$runtime->octaneServer}] does not support PHP {$phpVersion}."
            );
        }

        return $tag;
    }

    public function validate(RuntimeOptions $runtime, string $phpVersion): void
    {
        if (! $runtime->octane) {
            return;
        }

        if ($runtime->octaneServer === null) {
            throw new \InvalidArgumentException('An Octane server must be selected when Octane is enabled.');
        }

        if (! $this->catalog->isValidOctaneServer($runtime->octaneServer, $phpVersion)) {
            throw new \InvalidArgumentException(
                "Octane server [{$runtime->octaneServer}] does not support PHP {$phpVersion}."
            );
        }
    }

    /**
     * @return list<string>
     */
    public function runtimeFilesToPublish(RuntimeOptions $runtime): array
    {
        $files = [
            'php.ini',
            'start-container',
        ];

        if ($runtime->octane) {
            $files[] = $runtime->supervisordConfigName();

            if ($runtime->octaneServer === 'frankenphp') {
                $files[] = 'octane/Caddyfile';
            }
        } else {
            $files[] = 'supervisord.conf';
        }

        $files[] = $runtime->dockerfileName();

        return $files;
    }

    public function publishRuntime(string $phpVersion, RuntimeOptions $runtime, ServiceDependencyFlags $dependencies): void
    {
        $destination = $this->app->basePath('docker/'.$phpVersion);

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        foreach ($this->runtimeFilesToPublish($runtime) as $file) {
            if ($file === $runtime->dockerfileName()) {
                file_put_contents(
                    $destination.'/'.$file,
                    $this->stubRenderer->render($runtime, $phpVersion)
                );

                continue;
            }

            if ($file === $runtime->supervisordConfigName()) {
                $source = SailExtended::runtimePath($phpVersion, $file);

                if (! file_exists($source)) {
                    $source = SailExtended::runtimePath('8.5', $file);
                }

                if (file_exists($source)) {
                    File::copy($source, $destination.'/supervisord.conf');
                }

                continue;
            }

            $source = SailExtended::runtimePath($phpVersion, $file);
            $target = $destination.'/'.basename($file);

            if (str_contains($file, '/')) {
                $targetDir = dirname($destination.'/'.$file);
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $target = $destination.'/'.$file;
            }

            if (! file_exists($source)) {
                $source = SailExtended::runtimePath('8.5', basename($file));

                if (str_contains($file, '/')) {
                    $source = SailExtended::runtimePath('8.5', $file);
                }
            }

            if (! file_exists($source)) {
                continue;
            }

            File::copy($source, $target);
        }
    }
}
