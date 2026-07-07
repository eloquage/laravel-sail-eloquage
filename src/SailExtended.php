<?php

namespace Eloquage\LaravelSailExtended;

class SailExtended
{
    public static function packagePath(string $path = ''): string
    {
        $base = dirname(__DIR__);

        return $path === '' ? $base : $base.'/'.$path;
    }

    public static function stubPath(string $stub): string
    {
        return self::packagePath('stubs/'.$stub);
    }

    public static function runtimePath(string $phpVersion, string $file = ''): string
    {
        $base = self::packagePath('runtimes/'.$phpVersion);

        return $file === '' ? $base : $base.'/'.$file;
    }
}
