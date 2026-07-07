<?php

namespace Eloquage\LaravelSailExtended;

use Eloquage\LaravelSailExtended\Support\SailCommandRegistrar;
use Illuminate\Support\ServiceProvider;

class LaravelSailExtendedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sail-extended.php', 'sail-extended');

        $this->app->singleton(SailCommandRegistrar::class);

        $this->app->make(SailCommandRegistrar::class)->register();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sail-extended.php' => config_path('sail-extended.php'),
            ], 'sail-extended-config');

            $this->publishes([
                __DIR__.'/../runtimes' => $this->app->basePath('docker'),
            ], ['sail-extended', 'sail-extended-docker']);

            $this->publishes([
                __DIR__.'/../database' => $this->app->basePath('docker'),
            ], ['sail-extended', 'sail-extended-database']);

            if (file_exists(__DIR__.'/../bin/sail')) {
                $this->publishes([
                    __DIR__.'/../bin/sail' => $this->app->basePath('sail'),
                ], ['sail-extended', 'sail-extended-bin']);
            }
        }
    }
}
