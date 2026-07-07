<?php

use Eloquage\LaravelSailExtended\Console\AddCommand;
use Eloquage\LaravelSailExtended\Console\InstallCommand;
use Eloquage\LaravelSailExtended\Console\PublishCommand;
use Illuminate\Support\Facades\Artisan;

it('registers sail extended commands', function () {
    expect(Artisan::all())->toHaveKeys(['sail:install', 'sail:add', 'sail:publish']);
});

it('resolves sail install to extended command', function () {
    expect(Artisan::all()['sail:install'])->toBeInstanceOf(InstallCommand::class);
});

it('resolves sail add to extended command', function () {
    expect(Artisan::all()['sail:add'])->toBeInstanceOf(AddCommand::class);
});

it('resolves sail publish to extended command', function () {
    expect(Artisan::all()['sail:publish'])->toBeInstanceOf(PublishCommand::class);
});
