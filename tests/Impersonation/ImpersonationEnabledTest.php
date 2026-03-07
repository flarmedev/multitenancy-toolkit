<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Tenant;
use Tests\Feature\Fixtures\Models\ImpersonatedUser;
use Tests\Feature\Fixtures\Models\ImpersonatingTenant;

beforeEach(fn () => instantiate());

it('consumes a signed impersonation link and authenticates the tenant user', function () {
    $tenant = ImpersonatingTenant::query()->create([
        'name' => 'impersonation tenant',
        'domain' => 'impersonation-tenant.test',
        'database' => persistentTenantDatabasePath('impersonation-tenant'),
    ]);

    ensureTenantUsersTable($tenant);

    $user = ImpersonatedUser::query()->create([
        'name' => 'Tenant User',
        'email' => 'tenant.user@example.test',
        'password' => 'secret',
    ]);

    Tenant::forgetCurrent();

    $this->get($tenant->impersonate($user, '/tenant-dashboard'))
        ->assertRedirect('/tenant-dashboard');

    $this->assertAuthenticatedAs($user, 'web');

    expect((string) Tenant::current()?->getKey())->toBe((string) $tenant->getKey());
});

it('rejects token consumption when another tenant is already current', function () {
    $tenantA = ImpersonatingTenant::query()->create([
        'name' => 'tenant-a',
        'domain' => 'tenant-a.test',
        'database' => persistentTenantDatabasePath('tenant-a'),
    ]);
    $tenantB = ImpersonatingTenant::query()->create([
        'name' => 'tenant-b',
        'domain' => 'tenant-b.test',
        'database' => persistentTenantDatabasePath('tenant-b'),
    ]);

    ensureTenantUsersTable($tenantA);

    $user = ImpersonatedUser::query()->create([
        'name' => 'Tenant A User',
        'email' => 'tenant-a.user@example.test',
        'password' => 'secret',
    ]);

    Tenant::forgetCurrent();

    $url = $tenantA->impersonate($user);

    $tenantB->makeCurrent();

    $this->get($url)->assertForbidden();
    $this->assertGuest('web');
});

it('can issue impersonation URLs directly from the tenant trait', function () {
    $tenant = ImpersonatingTenant::query()->create([
        'name' => 'trait-tenant',
        'domain' => 'trait-tenant.test',
        'database' => persistentTenantDatabasePath('trait-tenant'),
    ]);

    ensureTenantUsersTable($tenant);

    $user = ImpersonatedUser::query()->create([
        'name' => 'Trait User',
        'email' => 'trait.user@example.test',
        'password' => 'secret',
    ]);

    Tenant::forgetCurrent();

    $url = $tenant->impersonate($user, '/trait-dashboard');

    $this->get($url)->assertRedirect('/trait-dashboard');

    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects impersonation links with tampered signatures', function () {
    $tenant = ImpersonatingTenant::query()->create([
        'name' => 'tamper-tenant',
        'domain' => 'tamper-tenant.test',
        'database' => persistentTenantDatabasePath('tamper-tenant'),
    ]);

    ensureTenantUsersTable($tenant);

    $user = ImpersonatedUser::query()->create([
        'name' => 'Tamper User',
        'email' => 'tamper.user@example.test',
        'password' => 'secret',
    ]);

    $url = $tenant->impersonate($user, '/safe-dashboard');
    $tampered = $url . '&redirect=%2Fevil-dashboard';

    $this->get($tampered)->assertForbidden();
});

function persistentTenantDatabasePath(string $tenantName): string
{
    $path = tempnam(sys_get_temp_dir(), 'mtk-' . $tenantName . '-');

    if (! is_string($path) || $path === '') {
        throw new \RuntimeException('Unable to allocate tenant database path for impersonation tests.');
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
