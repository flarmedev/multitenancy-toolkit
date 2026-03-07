<?php

use function Pest\Laravel\artisan;

describe('Single database setup', function () {
    it('runs rollback migrations on the default database', function () {
        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback')
            ->expectsOutputToContain('Defaulting to Laravel default rollback migrations.')
            ->expectsOutputToContain('0001_01_01_000000_create_direct_probe_table')
            ->expectsOutputToContain('0001_01_01_000001_create_landlord_probe_table')
            ->expectsOutputToContain('0001_01_01_000002_create_tenant_probe_table')
            ->assertExitCode(0);
    });

    it('delegates to laravel rollback migrations for an absolute path', function () {
        $path = realpath(__DIR__ . '/Fixtures/migrations');

        expect($path)->toBeString();

        artisan('tenancy:migrate', [
            '--path' => [$path],
            '--realpath' => true,
        ])->assertExitCode(0);

        artisan('tenancy:migrate:rollback', [
            '--path' => [$path],
            '--realpath' => true,
        ])
            ->expectsOutputToContain('Defaulting to Laravel default rollback migrations.')
            ->expectsOutputToContain('0001_01_01_000000_create_direct_probe_table')
            ->doesntExpectOutputToContain('0001_01_01_000001_create_landlord_probe_table')
            ->doesntExpectOutputToContain('0001_01_01_000002_create_tenant_probe_table')
            ->assertExitCode(0);
    });
});

describe('Multi database setup', function () {
    beforeEach(fn () => instantiate());

    it('runs rollback migrations across all databases', function () {
        makePersistentTenant(['name' => 'test one']);
        makePersistentTenant(['name' => 'test two']);

        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback')
            ->expectsOutputToContain('Running rollback migrations for landlord database.')
            ->expectsOutputToContain('Running rollback migrations for tenant test one')
            ->expectsOutputToContain('Running rollback migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs rollback migrations only for landlord when landlord scope is selected', function () {
        makePersistentTenant(['name' => 'test one']);

        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --landlord')
            ->expectsOutputToContain('Running rollback migrations for landlord database.')
            ->doesntExpectOutputToContain('Running rollback migrations for tenant')
            ->assertExitCode(0);
    });

    it('runs rollback migrations only for tenants when tenants scope is selected', function () {
        makePersistentTenant(['name' => 'test one']);
        makePersistentTenant(['name' => 'test two']);

        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --tenants')
            ->doesntExpectOutputToContain('Running rollback migrations for landlord database.')
            ->expectsOutputToContain('Running rollback migrations for tenant test one')
            ->expectsOutputToContain('Running rollback migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs rollback migrations only for the selected tenant', function () {
        $selected = makePersistentTenant(['name' => 'selected tenant']);
        makePersistentTenant(['name' => 'other tenant']);

        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --tenant=' . $selected->id)
            ->doesntExpectOutputToContain('Running rollback migrations for landlord database.')
            ->expectsOutputToContain('Running rollback migrations for tenant selected tenant')
            ->doesntExpectOutputToContain('Running rollback migrations for tenant other tenant')
            ->assertExitCode(0);
    });

    it('shows a message when no tenant matches the selected tenant id', function () {
        makePersistentTenant(['name' => 'available tenant']);

        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --tenant=999999')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('shows a message when tenants scope is selected without any tenant', function () {
        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --tenants')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('fails when more than one scope option is provided', function () {
        artisan('tenancy:migrate:rollback --landlord --tenant=1')
            ->expectsOutputToContain('Only one of --landlord, --tenants, or --tenant={id} can be provided at a time.')
            ->assertExitCode(1);
    });

    it('fails when landlord connection is not configured', function () {
        config()->set('multitenancy.landlord_database_connection_name', null);

        artisan('tenancy:migrate:rollback')
            ->expectsOutputToContain('No landlord database connection name configured.')
            ->assertExitCode(1);
    });

    it('delegates to laravel default rollback migrations when tenant setup is disabled', function () {
        config()->set('multitenancy.tenant_database_connection_name', null);

        artisan('tenancy:migrate:rollback --tenants')
            ->expectsOutputToContain('Defaulting to Laravel default rollback migrations.')
            ->assertExitCode(0);
    });

    it('delegates to laravel default rollback migrations when a database option is provided', function () {
        makePersistentTenant(['name' => 'test one']);
        artisan('tenancy:migrate')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --database=testing')
            ->expectsOutputToContain('Defaulting to Laravel default rollback migrations.')
            ->doesntExpectOutputToContain('Running rollback migrations for landlord database.')
            ->doesntExpectOutputToContain('Running rollback migrations for tenant')
            ->assertExitCode(0);
    });

    it('returns success in graceful mode when a tenant rollback throws', function () {
        makePersistentTenant(['name' => 'test one']);

        app('tenant-migrator')->tenantPath(__DIR__ . '/Fixtures/migrations/tenant/failing-rollback');
        $message = 'Intentional failing tenant rollback migration.';

        artisan('tenancy:migrate --tenants')->assertExitCode(0);

        artisan('tenancy:migrate:rollback --tenants --graceful')
            ->expectsOutputToContain('Running rollback migrations for tenant test one')
            ->expectsOutputToContain($message)
            ->assertExitCode(0);
    });
});
