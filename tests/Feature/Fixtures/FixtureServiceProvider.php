<?php

namespace Tests\Feature\Fixtures;

use Flarme\MultitenancyToolkit\ServiceProvider;

class FixtureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/migrations');
        $this->loadLandlordMigrationsFrom(__DIR__ . '/migrations/landlord');
        $this->loadTenantMigrationsFrom(__DIR__ . '/migrations/tenant');
    }
}
