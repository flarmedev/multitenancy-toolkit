<?php

namespace Flarme\MultitenancyToolkit;

use Flarme\MultitenancyToolkit\Console\Commands\Migrations\FreshCommand;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\MigrateCommand;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\RollbackCommand;
use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseFreshCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseMigrateCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand as BaseRollbackCommand;

class MultitenancyToolkitProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/multitenancy-toolkit.php', 'multitenancy-toolkit');

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

    public function boot(): void
    {
        $this->app['router']->middlewareGroup('tenant', config('multitenancy-toolkit.tenant_middlewares'));

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [dirname(__DIR__) . '/config/multitenancy-toolkit.php' => config_path('multitenancy-toolkit.php')],
                'multitenancy-toolkit-config'
            );
        }

        if ($this->enablesMigrationsAutoload()) {
            $this->loadLandlordMigrationsFrom(database_path('migrations/landlord'));
            $this->loadTenantMigrationsFrom(database_path('migrations/tenant'));
        }

        if ($this->enablesImpersonation()) {
            $this->loadRoutesFrom(dirname(__DIR__) . '/routes/impersonation.php');
        }
    }

    protected function enablesImpersonation(): bool
    {
        return (bool) config('multitenancy-toolkit.impersonation.enabled', false);
    }

    protected function enablesMigrationsAutoload(): bool
    {
        return (bool) config('multitenancy-toolkit.register_migrations_directories', true);
    }
}
