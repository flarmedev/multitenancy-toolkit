<?php

use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

describe('Single database setup', function () {
    it('runs fresh migrations on the default database', function () {
        artisan('migrate:fresh')
            ->expectsOutputToContain('Running fresh migrations on the default database.')
            ->expectsOutputToContain('0001_01_01_000000_create_direct_probe_table')
            ->expectsOutputToContain('0001_01_01_000001_create_landlord_probe_table')
            ->expectsOutputToContain('0001_01_01_000002_create_tenant_probe_table')
            ->assertExitCode(0);
    });
});

describe('Multi database setup', function () {
    beforeEach(fn () => instantiate());

    it('runs fresh migrations across all databases', function () {
        makeTenant(['name' => 'test one']);
        makeTenant(['name' => 'test two']);

        artisan('migrate:fresh')
            ->expectsOutputToContain('Running fresh migrations for landlord database.')
            ->expectsOutputToContain('Running fresh migrations for tenant test one')
            ->expectsOutputToContain('Running fresh migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs fresh migrations only for landlord when landlord scope is selected', function () {
        makeTenant(['name' => 'test one']);

        artisan('migrate:fresh --landlord')
            ->expectsOutputToContain('Running fresh migrations for landlord database.')
            ->doesntExpectOutputToContain('Running fresh migrations for tenant')
            ->assertExitCode(0);
    });

    it('runs fresh migrations only for tenants when tenants scope is selected', function () {
        makeTenant(['name' => 'test one']);
        makeTenant(['name' => 'test two']);

        artisan('migrate:fresh --tenants')
            ->doesntExpectOutputToContain('Running fresh migrations for landlord database.')
            ->expectsOutputToContain('Running fresh migrations for tenant test one')
            ->expectsOutputToContain('Running fresh migrations for tenant test two')
            ->assertExitCode(0);
    });

    it('runs fresh migrations only for the selected tenant', function () {
        $selected = makeTenant(['name' => 'selected tenant']);
        makeTenant(['name' => 'other tenant']);

        artisan('migrate:fresh --tenant=' . $selected->id)
            ->doesntExpectOutputToContain('Running fresh migrations for landlord database.')
            ->expectsOutputToContain('Running fresh migrations for tenant selected tenant')
            ->doesntExpectOutputToContain('Running fresh migrations for tenant other tenant')
            ->assertExitCode(0);
    });

    it('shows a message when no tenant matches the selected tenant id', function () {
        makeTenant(['name' => 'available tenant']);

        artisan('migrate:fresh --tenant=999999')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('shows a message when tenants scope is selected without any tenant', function () {
        artisan('migrate:fresh --tenants')
            ->expectsOutputToContain('No tenant found.')
            ->assertExitCode(0);
    });

    it('shows a message when tenant table does not exist on landlord database', function () {
        Schema::dropIfExists('tenants');

        artisan('migrate:fresh --tenants')
            ->expectsOutputToContain('Tenants table not found on landlord database.')
            ->assertExitCode(0);
    });

    it('fails when more than one scope option is provided', function () {
        artisan('migrate:fresh --landlord --tenant=1')
            ->expectsOutputToContain('Only one of --landlord, --tenants, or --tenant={id} can be provided at a time.')
            ->assertExitCode(1);
    });

    it('fails when landlord connection is not configured', function () {
        config()->set('multitenancy.landlord_database_connection_name', null);

        artisan('migrate:fresh')
            ->expectsOutputToContain('No landlord database connection name configured.')
            ->assertExitCode(1);
    });

    it('runs default fresh flow when tenant setup is disabled', function () {
        config()->set('multitenancy.tenant_database_connection_name', null);

        artisan('migrate:fresh --tenants')
            ->expectsOutputToContain('Running fresh migrations on the default database.')
            ->assertExitCode(0);
    });

    it('drops tenant databases when landlord tenants table is dropped', function () {
        $first = makePersistentTenant(['name' => 'test one']);
        $second = makePersistentTenant(['name' => 'test two']);

        expect(file_exists($first->database))->toBeTrue()
            ->and(file_exists($second->database))->toBeTrue();

        artisan('migrate:fresh --landlord')->assertExitCode(0);

        expect(file_exists($first->database))->toBeFalse()
            ->and(file_exists($second->database))->toBeFalse();
    });
});
