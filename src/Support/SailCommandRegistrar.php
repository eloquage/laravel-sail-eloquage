<?php

namespace Eloquage\LaravelSailExtended\Support;

use Eloquage\LaravelSailExtended\Console\AddCommand;
use Eloquage\LaravelSailExtended\Console\InstallCommand;
use Eloquage\LaravelSailExtended\Console\PublishCommand;
use Illuminate\Contracts\Foundation\Application;

class SailCommandRegistrar
{
    public function __construct(
        protected Application $app,
    ) {}

    public function register(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $replacements = [
            \Laravel\Sail\Console\InstallCommand::class => InstallCommand::class,
            \Laravel\Sail\Console\AddCommand::class => AddCommand::class,
            \Laravel\Sail\Console\PublishCommand::class => PublishCommand::class,
        ];

        foreach ($replacements as $sailCommand => $replacement) {
            $this->app->bind($sailCommand, $replacement);
        }
    }
}
