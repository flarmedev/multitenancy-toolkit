<?php

use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\Database\Factories\TenantFactory;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;

function instantiate(): void
{
    Schema::create('tenants', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('domain');
        $table->string('database');
        $table->timestamps();
    });

    config()->set('multitenancy.switch_tenant_tasks', [SwitchTenantDatabaseTask::class]);
    config()->set('multitenancy.landlord_database_connection_name', 'testing');
    config()->set(
        'database.connections.tenant',
        array_merge(
            config('database.connections.testing'),
            ['database' => null, 'url' => true]
        )
    );
    config()->set('multitenancy.tenant_database_connection_name', 'tenant');
}

function makeTenant(array $attributes = []): Tenant
{
    $name = fake()->company();
    $slug = str()->slug($name);
    $database = "file:{$slug}?mode=memory&cache=shared";

    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()
        ->create(array_merge([
            'name' => $name,
            'database' => $database,
        ], $attributes));

    return $tenant;
}

function makePersistentTenant(array $attributes = []): Tenant
{
    $databasePath = tempnam(sys_get_temp_dir(), 'mtk-tenant-');

    return makeTenant(array_merge([
        'database' => $databasePath,
    ], $attributes));
}
