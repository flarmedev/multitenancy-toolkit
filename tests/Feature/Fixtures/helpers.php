<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Database\Factories\TenantFactory;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;
use Tests\Feature\Fixtures\Models\ImpersonatedUser;
use Tests\Feature\Fixtures\Models\ImpersonatingTenant;

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

function enableImpersonationForTests(): void
{
    config()->set('multitenancy-toolkit.impersonation.enabled', true);
    config()->set('multitenancy.tenant_model', ImpersonatingTenant::class);
    config()->set('auth.providers.users.model', ImpersonatedUser::class);
    config()->set('auth.guards.web.provider', 'users');

    if (! app('router')->has('multitenancy-toolkit.impersonate')) {
        require dirname(__DIR__, 3) . '/routes/impersonation.php';
    }
}

function persistentTenantDatabasePath(string $tenantName): string
{
    $path = tempnam(sys_get_temp_dir(), 'mtk-' . $tenantName . '-');

    if (! is_string($path) || $path === '') {
        throw new RuntimeException('Unable to allocate tenant database path for impersonation tests.');
    }

    return $path;
}

function ensureTenantUsersTable(Tenant $tenant): void
{
    $tenant->makeCurrent();

    if (Schema::connection('tenant')->hasTable('users')) {
        return;
    }

    Schema::connection('tenant')->create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
    });
}
