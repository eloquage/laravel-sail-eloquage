<?php

namespace Eloquage\LaravelSailExtended\Support;

use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\Process\Process;

class SailProcessRunner
{
    public function __construct(
        protected Application $app,
    ) {}

    public function isAvailable(): bool
    {
        return file_exists($this->app->basePath('vendor/bin/sail'));
    }

    /**
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeout = 600): int
    {
        $process = new Process(
            array_merge([$this->app->basePath('vendor/bin/sail')], $command),
            $this->app->basePath(),
        );
        $process->setTimeout($timeout);

        return $process->run();
    }
}
