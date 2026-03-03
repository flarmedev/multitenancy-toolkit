<?php

use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Flarme\MultitenancyToolkit\MultitenancyToolkitProvider;

beforeEach(function () {
    $this->app->register(MultitenancyToolkitProvider::class);
});

it('instantiates the custom migrator', function () {
    expect($this->app['migrator'])->toBeInstanceOf(Migrator::class);
});

it('can register landlord migrations', function () {
    $this->app['migrator']->landlordPath('landlord_migration_path');

    expect($this->app['migrator']->landlordPaths())->toContain('landlord_migration_path');
});

it('can register tenant migrations', function () {
    $this->app['migrator']->tenantPath('tenant_migration_path');

    expect($this->app['migrator']->tenantPaths())->toContain('tenant_migration_path');
});
