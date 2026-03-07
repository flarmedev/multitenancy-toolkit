<?php

use function Pest\Laravel\artisan;

describe('Single database setup', function () {
    it('runs all migrations on the default database', function () {
        artisan('tenancy:migrate')
            ->expectsOutputToContain('Defaulting to Laravel default migrations.')
            ->expectsOutputToContain('0001_01_01_000000_create_direct_probe_table')
            ->expectsOutputToContain('0001_01_01_000001_create_landlord_probe_table')
            ->expectsOutputToContain('0001_01_01_000002_create_tenant_probe_table')
            ->assertExitCode(0);
    });
});

describe('Multi database setup', function () {
    beforeEach(fn () => instantiate());

    it('runs migrations across all databases', function () {
        makeTenant(['name' => 'test one']);
        makeTenant(['name' => 'test two']);

        artisan('tenancy:migrate')
            ->expectsOutputToContain('Running migrations for landlord database.')
            ->expectsOutputToContain('Running migrations for tenant test one')
            ->expectsOutputToContain('Running migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs only landlord migrations when landlord scope is selected', function () {
        makeTenant(['name' => 'test one']);

        artisan('tenancy:migrate --landlord')
            ->expectsOutputToContain('Running migrations for landlord database.')
            ->doesntExpectOutputToContain('Running migrations for tenant')
            ->assertExitCode(0);
    });

    it('runs only tenant migrations when tenants scope is selected', function () {
        makeTenant(['name' => 'test one']);
        makeTenant(['name' => 'test two']);

        artisan('tenancy:migrate --tenants')
            ->doesntExpectOutputToContain('Running migrations for landlord database.')
            ->expectsOutputToContain('Running migrations for tenant test one')
            ->expectsOutputToContain('Running migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs migrations only for the selected tenant', function () {
        $selected = makeTenant(['name' => 'selected tenant']);
        makeTenant(['name' => 'other tenant']);

        artisan('tenancy:migrate --tenant=' . $selected->id)
            ->doesntExpectOutputToContain('Running migrations for landlord database.')
            ->expectsOutputToContain('Running migrations for tenant selected tenant')
            ->doesntExpectOutputToContain('Running migrations for tenant other tenant')
            ->assertExitCode(0);
    });

    it('shows a message when no tenant matches the selected tenant id', function () {
        makeTenant(['name' => 'available tenant']);

        artisan('tenancy:migrate --tenant=999999')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('shows a message when tenants scope is selected without any tenant', function () {
        artisan('tenancy:migrate --tenants')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('fails when more than one scope option is provided', function () {
        artisan('tenancy:migrate --landlord --tenant=1')
            ->expectsOutputToContain('Only one of --landlord, --tenants, or --tenant={id} can be provided at a time.')
            ->assertExitCode(1);
    });

    it('fails when landlord connection is not configured', function () {
        config()->set('multitenancy.landlord_database_connection_name', null);

        artisan('tenancy:migrate')
            ->expectsOutputToContain('No landlord database connection name configured.')
            ->assertExitCode(1);
    });

    it('delegates to laravel default migrations when a database option is provided', function () {
        makeTenant(['name' => 'test one']);

        artisan('tenancy:migrate --database=testing')
            ->expectsOutputToContain('Defaulting to Laravel default migrations.')
            ->doesntExpectOutputToContain('Running migrations for landlord database.')
            ->doesntExpectOutputToContain('Running migrations for tenant')
            ->assertExitCode(0);
    });

    it('returns success in graceful mode when a tenant migration throws', function () {
        makeTenant(['name' => 'test one']);

        app('tenant-migrator')->tenantPath(__DIR__ . '/Fixtures/migrations/tenant/failing');
        $message = 'Intentional failing tenant migration.';

        artisan('tenancy:migrate --tenants --graceful')
            ->expectsOutputToContain('Running migrations for tenant test one')
            ->expectsOutputToContain($message)
            ->assertExitCode(0);
    });
});
