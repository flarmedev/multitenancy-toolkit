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
        $this->callAfterResolving('tenant-migrator', function ($migrator) use ($paths) {
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
        $this->callAfterResolving('tenant-migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                /** @var Migrator $migrator */
                $migrator->tenantPath($path);
            }
        });
    }

    protected function loadMigrationsFrom($paths): void
    {
        $this->callAfterResolving('tenant-migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                $migrator->path($path);
            }
        });
    }
}
