<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits;

use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Contracts\IsTenant;
use Throwable;

trait HandlesTenantCommands
{
    use InteractsWithIO;

    protected function currentTenant(): ?IsTenant
    {
        return app(IsTenant::class)::current();
    }

    protected function checkTenant(): bool
    {
        return app(IsTenant::class)::checkCurrent();
    }

    protected function usesMultipleDatabasesSetup(): bool
    {
        return (bool) config('multitenancy.tenant_database_connection_name');
    }

    protected function landlordConnectionName(): ?string
    {
        return config('multitenancy.landlord_database_connection_name');
    }

    protected function tenantConnectionName(): ?string
    {
        return config('multitenancy.tenant_database_connection_name');
    }

    protected function resolveLandlordConnectionOrFail(): ?string
    {
        $connection = $this->landlordConnectionName();

        if ($connection) {
            return $connection;
        }

        $this->components->error('No landlord database connection name configured.');

        return null;
    }

    protected function resolveTenantConnectionOrFail(): ?string
    {
        $connection = $this->tenantConnectionName();

        if ($connection) {
            return $connection;
        }

        $this->components->error('No tenant database connection name configured.');

        return null;
    }

    protected function ensureScopeOptionsAreValid(): bool
    {
        $selected = (int) ($this->input->hasOption('landlord') && $this->option('landlord'))
            + (int) ($this->input->hasOption('tenants') && $this->option('tenants'))
            + (int) ($this->input->hasOption('tenant') && $this->option('tenant'));

        if ($selected <= 1) {
            return true;
        }

        $this->components->error('Only one of --landlord, --tenants, or --tenant={id} can be provided at a time.');

        return false;
    }

    protected function shouldDelegate(): bool
    {
        return !$this->usesMultipleDatabasesSetup()
            || ($this->hasOption('database') && $this->option('database'))
            || ($this->hasOption('path') && $this->option('path'));
    }

    protected function shouldRunAgainstLandlord(): bool
    {
        if (
            ($this->hasOption('tenants') && $this->option('tenants'))
            || ($this->hasOption('tenant') && $this->option('tenant'))
        ) {
            return false;
        }

        return true;
    }

    protected function shouldRunAgainstTenant(): bool
    {
        if ($this->hasOption('landlord') && $this->option('landlord')) {
            return false;
        }

        return true;
    }

    protected function hasTenantTable(string $landlordConnection): bool
    {
        try {
            return Schema::connection($landlordConnection)->hasTable(app(IsTenant::class)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    protected function selectedTenants(string $landlordConnection, ?bool &$tenantTableExists = null): EloquentCollection
    {
        $tenantTableExists = $this->hasTenantTable($landlordConnection);

        if (!$tenantTableExists) {
            return new EloquentCollection;
        }

        if ($tenant = $this->option('tenant')) {
            /** @phpstan-ignore-next-line */
            return app(IsTenant::class)::where('id', $tenant)->get();
        }

        return app(IsTenant::class)::all();
    }

    protected function tenantTableExistsOrReport(bool $tenantTableExists): bool
    {
        if ($tenantTableExists) {
            return true;
        }

        $this->components->info('Tenants table not found on landlord database.');

        return false;
    }

    protected function tenantsAvailableOrReport(EloquentCollection $tenants): bool
    {
        if ($tenants->isNotEmpty()) {
            return true;
        }

        $this->components->info('No tenant found.');

        return false;
    }

    /**
     * @param  callable(IsTenant): void  $callback
     */
    protected function runForEachTenant(Collection $tenants, string $operation, callable $callback): void
    {
        $tenants->each(function (IsTenant $tenant) use ($operation, $callback): void {
            $tenant->makeCurrent();
            $current = $tenant->name ?? $tenant->getKey();

            $this->components->info("Running {$operation} for tenant {$current}");

            $callback($tenant);

            $tenant->forget();
        });
    }
}
