<?php

namespace Eloquage\LaravelSailExtended\Services;

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\SailExtended;

class RuntimeStubRenderer
{
    public function dockerfileStubName(RuntimeOptions $runtime): string
    {
        return $runtime->dockerfileName();
    }

    public function render(RuntimeOptions $runtime, string $phpVersion): string
    {
        $stubName = $this->dockerfileStubName($runtime);
        $path = SailExtended::stubPath('docker/'.$stubName.'.stub');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Dockerfile stub not found: docker/{$stubName}.stub");
        }

        $phpPkg = 'php'.str_replace('.', '', $phpVersion);

        $replacements = [
            '{{PHP_VERSION}}',
            '{{PHP_PKG}}',
            '{{PHP_INI_PATH}}',
            '{{XDEBUG_PACKAGE}}',
            '{{ROADRUNNER_VERSION}}',
        ];

        $values = [
            $phpVersion,
            $phpPkg,
            $this->phpIniPath($phpPkg),
            $phpPkg.'-pecl-xdebug',
            $this->roadrunnerVersion(),
        ];

        return str_replace($replacements, $values, $contents);
    }

    protected function roadrunnerVersion(): string
    {
        $config = require dirname(__DIR__, 2).'/config/sail-extended.php';

        return (string) ($config['octane']['roadrunner_version'] ?? '2025.1.1');
    }

    protected function phpIniPath(string $phpPkg): string
    {
        return '/etc/'.$phpPkg.'/php.ini';
    }
}
