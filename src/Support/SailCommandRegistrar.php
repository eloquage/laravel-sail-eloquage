<?php

namespace Eloquage\LaravelSailExtended\Support;

use Eloquage\LaravelSailExtended\Console\AddCommand;
use Eloquage\LaravelSailExtended\Console\InstallCommand;
use Eloquage\LaravelSailExtended\Console\PublishCommand;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use Illuminate\Contracts\Foundation\Application;
use ReflectionMethod;

class SailCommandRegistrar
{
    /**
     * @var list<string>
     */
    protected array $commandsToOverride = [
        'sail:install',
        'sail:add',
        'sail:publish',
    ];

    /**
     * @var list<class-string>
     */
    protected array $replacementCommands = [
        InstallCommand::class,
        AddCommand::class,
        PublishCommand::class,
    ];

    public function __construct(
        protected Application $app,
    ) {}

    public function register(): void
    {
        $this->app->booted(function (): void {
            $kernel = $this->app->make(ConsoleKernelContract::class);
            $kernel->addCommands($this->replacementCommands);

            $artisan = $this->artisan($kernel);

            $artisan->resolveCommands($this->replacementCommands);
        });
    }

    protected function artisan(ConsoleKernelContract $kernel): \Illuminate\Console\Application
    {
        $method = new ReflectionMethod($kernel, 'getArtisan');

        /** @var \Illuminate\Console\Application $artisan */
        $artisan = $method->invoke($kernel);

        return $artisan;
    }
}
