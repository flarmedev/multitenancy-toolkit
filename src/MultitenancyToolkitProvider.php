<?php

namespace Flarme\MultitenancyToolkit;

use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class MultitenancyToolkitProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('migrator', function ($app) {
            $repository = $app['migration.repository'];

            return new Migrator($repository, $app['db'], $app['files'], $app['events']);
        });
    }
}
