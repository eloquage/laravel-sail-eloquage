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

        return str_replace(
            [
                '{{PHP_VERSION}}',
                '{{PHP_PKG}}',
                '{{PHP_INI_PATH}}',
                '{{XDEBUG_PACKAGE}}',
            ],
            [
                $phpVersion,
                $phpPkg,
                $this->phpIniPath($phpPkg),
                $phpPkg.'-pecl-xdebug',
            ],
            $contents
        );
    }

    protected function phpIniPath(string $phpPkg): string
    {
        return '/etc/'.$phpPkg.'/php.ini';
    }
}
