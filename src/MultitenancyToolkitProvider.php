<?php

namespace Flarme\MultitenancyToolkit;

use Flarme\MultitenancyToolkit\Console\Commands\Migrations\FreshCommand;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\MigrateCommand;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\RollbackCommand;
use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand as BaseRollbackCommand;
use Illuminate\Support\ServiceProvider;

class MultitenancyToolkitProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('tenant-migrator', function ($app) {
            $repository = $app['migration.repository'];

            return new Migrator($repository, $app['db'], $app['files'], $app['events']);
        });

        $this->app->singleton(BaseMigrateCommand::class, function ($app) {
            return new MigrateCommand($app['tenant-migrator'], $app['events']);
        });

        $this->app->singleton(BaseFreshCommand::class, function ($app) {
            return new FreshCommand($app['tenant-migrator']);
        });

        $this->app->singleton(BaseRollbackCommand::class, function ($app) {
            return new RollbackCommand($app['tenant-migrator']);
        });
    }
}
