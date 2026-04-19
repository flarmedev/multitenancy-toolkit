<?php

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\DomainTenantFinder;
use Tests\Feature\Fixtures\Models\ImpersonatedUser;
use Tests\Feature\Fixtures\Models\ImpersonatingTenant;

describe('Impersonation disabled', function () {
    it('does not register impersonation route by default', function () {
        expect(app('router')->has('multitenancy-toolkit.impersonate'))->toBeFalse();
    });

    it('throws a clear error when tenant trait is used while disabled', function () {
        $tenant = new ImpersonatingTenant;

        expect(fn () => $tenant->impersonate(1))
            ->toThrow(LogicException::class, 'Tenant impersonation is not enabled.');
    });
});

describe('Impersonation enabled', function () {
    beforeEach(function () {
        instantiate();
        enableImpersonationForTests();
        config()->set('multitenancy.tenant_finder', DomainTenantFinder::class);
    });

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

    it('uses the current tenant request origin when generating impersonation URLs', function () {
        $tenant = ImpersonatingTenant::query()->create([
            'name' => 'domain-tenant',
            'domain' => 'domain-tenant.test',
            'database' => persistentTenantDatabasePath('domain-tenant'),
        ]);

        ensureTenantUsersTable($tenant);
        $tenant->makeCurrent();

        $user = ImpersonatedUser::query()->create([
            'name' => 'Domain User',
            'email' => 'domain.user@example.test',
            'password' => 'secret',
        ]);

        app()->instance('request', Request::create('https://domain-tenant.test/current-dashboard', 'GET'));

        $generatedUrl = $tenant->impersonate($user, '/domain-dashboard');

        expect($generatedUrl)->toStartWith('https://domain-tenant.test/');
    });

    it('can resolve a tenant-specific impersonation origin from the central domain', function () {
        $tenant = ImpersonatingTenant::query()->create([
            'name' => 'central-issued-tenant',
            'domain' => 'central-issued-tenant.test',
            'database' => persistentTenantDatabasePath('central-issued-tenant'),
        ]);

        ensureTenantUsersTable($tenant);

        $user = ImpersonatedUser::query()->create([
            'name' => 'Central User',
            'email' => 'central.user@example.test',
            'password' => 'secret',
        ]);

        app()->instance('request', Request::create('https://central.test/admin/tenants', 'GET'));

        $generatedUrl = $tenant->impersonate($user, '/tenant-dashboard');

        expect($generatedUrl)->toStartWith('https://central-issued-tenant.test/');
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
});
