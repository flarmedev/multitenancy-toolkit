<?php

namespace Flarme\MultitenancyToolkit;

use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

abstract class ServiceProvider extends BaseServiceProvider
{
    /**
     * @param  string|list<string>  $paths
     */
    protected function loadLandlordMigrationsFrom(array | string $paths): void
    {
        $this->callAfterResolving('migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                /** @var Migrator $migrator */
                $migrator->landlordPath($path);
            }
        });
    }

    /**
     * @param  string|list<string>  $paths
     */
    protected function loadTenantMigrationsFrom(array | string $paths): void
    {
        $this->callAfterResolving('migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                /** @var Migrator $migrator */
                $migrator->tenantPath($path);
            }
        });
    }
}
