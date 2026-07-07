<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\DevelopmentSupervisorOptions;
use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\SailExtended;
use Illuminate\Contracts\Foundation\Application;

class SupervisordScaffolder
{
    public function __construct(
        protected Application $app,
        protected PackageManagerDetector $packageManagerDetector,
    ) {}

    public function apply(RuntimeOptions $runtime, DevelopmentSupervisorOptions $options, string $phpVersion): void
    {
        $dockerDir = $this->app->basePath('docker/'.$phpVersion);
        $baseConfig = $dockerDir.'/supervisord.conf';

        if (! file_exists($baseConfig)) {
            $baseConfig = $dockerDir.'/'.$runtime->supervisordConfigName();

            if (! file_exists($baseConfig)) {
                return;
            }
        }

        $contents = file_get_contents($baseConfig);

        if ($contents === false) {
            return;
        }

        $fragments = [];

        if ($options->schedule) {
            $fragments[] = $this->loadFragment('supervisord.schedule.conf');
        }

        if ($options->horizon) {
            $fragments[] = $this->loadFragment('supervisord.horizon.conf');
        }

        if ($options->vite && $options->packageManager !== null) {
            $devCommand = $this->packageManagerDetector->devCommand($options->packageManager);

            if ($devCommand !== null) {
                $fragments[] = str_replace(
                    '{{DEV_COMMAND}}',
                    $devCommand,
                    $this->loadFragment('supervisord.vite.conf')
                );
            }
        }

        $contents = $this->stripOptionalPrograms($contents);

        foreach ($fragments as $fragment) {
            if (! str_contains($contents, $this->programName($fragment))) {
                $contents = rtrim($contents).PHP_EOL.PHP_EOL.$fragment;
            }
        }

        file_put_contents($dockerDir.'/supervisord.conf', rtrim($contents).PHP_EOL);
    }

    /**
     * @return array<string, mixed>
     */
    public function productionWorkerServices(
        RuntimeOptions $runtime,
        DevelopmentSupervisorOptions $options,
        string $phpVersion,
        bool $requiresLocalBuild = true,
    ): array {
        $services = [];
        $image = $requiresLocalBuild
            ? 'sail-'.$phpVersion.'/app'
            : app(RegistryImageResolver::class)->imageReference($runtime, $phpVersion, 'production');
        $build = [
            'context' => './docker/'.$phpVersion,
            'dockerfile' => $runtime->dockerfileName(),
            'target' => 'production',
            'args' => [
                'WWWGROUP' => '${WWWGROUP}',
            ],
        ];

        if ($options->horizon) {
            $services['horizon'] = [
                'image' => $image,
                'command' => ['php', 'artisan', 'horizon:listen'],
                'depends_on' => ['redis'],
                'volumes' => ['.:/var/www/html'],
                'networks' => ['sail'],
            ];

            if ($requiresLocalBuild) {
                $services['horizon']['build'] = $build;
            } else {
                $services['horizon']['pull_policy'] = 'missing';
            }
        }

        if ($options->schedule) {
            $services['scheduler'] = [
                'image' => $image,
                'command' => ['php', 'artisan', 'schedule:work'],
                'volumes' => ['.:/var/www/html'],
                'networks' => ['sail'],
            ];

            if ($requiresLocalBuild) {
                $services['scheduler']['build'] = $build;
            } else {
                $services['scheduler']['pull_policy'] = 'missing';
            }
        }

        return $services;
    }

    protected function loadFragment(string $filename): string
    {
        $path = SailExtended::runtimePath('8.5', $filename);
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Supervisord fragment not found: {$filename}");
        }

        return $contents;
    }

    protected function stripOptionalPrograms(string $contents): string
    {
        return preg_replace(
            '/\n\[program:(schedule|horizon|vite)\][^\[]*/s',
            '',
            $contents
        ) ?? $contents;
    }

    protected function programName(string $fragment): string
    {
        if (preg_match('/\[program:([^\]]+)\]/', $fragment, $matches)) {
            return '[program:'.$matches[1].']';
        }

        return '';
    }
}
