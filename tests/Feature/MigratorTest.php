<?php

use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Flarme\MultitenancyToolkit\MultitenancyToolkitProvider;

beforeEach(function () {
    $this->app->register(MultitenancyToolkitProvider::class);
});

it('instantiates the custom tenant-migrator', function () {
    expect($this->app['tenant-migrator'])->toBeInstanceOf(Migrator::class);
});

it('can register landlord migrations', function () {
    $this->app['tenant-migrator']->landlordPath('landlord_migration_path');

    expect($this->app['tenant-migrator']->landlordPaths())->toContain('landlord_migration_path');
});

it('can register tenant migrations', function () {
    $this->app['tenant-migrator']->tenantPath('tenant_migration_path');

    expect($this->app['tenant-migrator']->tenantPaths())->toContain('tenant_migration_path');
});
