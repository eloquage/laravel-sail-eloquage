#!/usr/bin/env php
<?php

declare(strict_types=1);

use Eloquage\LaravelSailExtended\Data\RuntimeOptions;
use Eloquage\LaravelSailExtended\Services\RuntimeStubRenderer;
use Eloquage\LaravelSailExtended\SailExtended;

$packageRoot = dirname(__DIR__, 2);

require $packageRoot.'/vendor/autoload.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: prepare-docker-context.php <php-version> <runtime>\n");
    fwrite(STDERR, "Runtime: serve, frankenphp, swoole, roadrunner\n");
    exit(1);
}

$phpVersion = $argv[1];
$runtimeName = $argv[2];

$runtime = match ($runtimeName) {
    'serve' => new RuntimeOptions,
    'frankenphp' => new RuntimeOptions(octane: true, octaneServer: 'frankenphp'),
    'swoole' => new RuntimeOptions(octane: true, octaneServer: 'swoole'),
    'roadrunner' => new RuntimeOptions(octane: true, octaneServer: 'roadrunner'),
    default => throw new InvalidArgumentException("Unknown runtime [{$runtimeName}]."),
};

$outputDirectory = $packageRoot.'/.docker-build/'.$phpVersion.'-'.$runtimeName;

if (is_dir($outputDirectory)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($outputDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
} else {
    mkdir($outputDirectory, 0755, true);
}

$renderer = new RuntimeStubRenderer;
$dockerfileName = $runtime->dockerfileName();

file_put_contents(
    $outputDirectory.'/'.$dockerfileName,
    $renderer->render($runtime, $phpVersion)
);

$runtimeSource = SailExtended::runtimePath($phpVersion);
$fallbackSource = SailExtended::runtimePath('8.5');

$filesToCopy = [
    'php.ini',
    'start-container',
    $runtime->supervisordConfigName() => 'supervisord.conf',
];

if ($runtime->octane && $runtime->octaneServer === 'frankenphp') {
    $filesToCopy['octane/Caddyfile'] = 'octane/Caddyfile';
}

foreach ($filesToCopy as $source => $target) {
    if (is_int($source)) {
        $source = $target;
    }

    $sourcePath = $runtimeSource.'/'.$source;

    if (! file_exists($sourcePath)) {
        $sourcePath = $fallbackSource.'/'.$source;
    }

    if (! file_exists($sourcePath)) {
        continue;
    }

    $targetPath = $outputDirectory.'/'.$target;

    if (str_contains($target, '/')) {
        $targetDir = dirname($targetPath);

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
    }

    copy($sourcePath, $targetPath);
}

fwrite(STDOUT, $outputDirectory.PHP_EOL);
