<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations;

use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\HandlesTenantCommands;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\HandlesTenantDatabasesDrop;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\ResolvesMigrationPaths;
use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Database\Console\Migrations\RollbackCommand as BaseCommand;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Spatie\Multitenancy\Models\Tenant;
use Throwable;

class RollbackCommand extends BaseCommand
{
    use HandlesTenantCommands;
    use HandlesTenantDatabasesDrop;
    use ResolvesMigrationPaths;

    /**
     * The migrator instance.
     *
     * @var Migrator
     */
    protected $migrator;

    protected $signature = 'migrate:rollback
                {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--pretend : Dump the SQL queries that would be run}
                {--step= : The number of migrations to be reverted}
                {--batch= : The batch of migrations (identified by their batch number) to be reverted}
                {--graceful : Return a successful exit code even if an error occurs}
                {--landlord : Run migrations only for the landlord database}
                {--tenants : Run migrations only for all tenant databases}
                {--tenant= : Run migrations only for the given tenant id}';

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if ($this->shouldDelegate()) {
            $this->components->info('Defaulting to Laravel default rollback migrations.');

            return (int) parent::handle();
        }

        if (! $this->ensureScopeOptionsAreValid()) {
            return self::FAILURE;
        }

        $defaultConnection = $this->resolveLandlordConnectionOrFail();
        if (! $defaultConnection) {
            return self::FAILURE;
        }

        $tenantTableExistedBefore = false;
        $tenantDatabases = [];
        $tenants = new EloquentCollection;
        $tenantTableExists = true;

        if ($this->shouldRunAgainstLandlord()) {
            $tenantTableExistedBefore = $this->hasTenantTable($defaultConnection);

            if ($tenantTableExistedBefore) {
                $tenantDatabases = $this->getTenantDatabaseNames(Tenant::all());
            }
        }

        if ($this->shouldRunAgainstTenant()) {
            $connection = $this->resolveTenantConnectionOrFail();
            if (! $connection) {
                return self::FAILURE;
            }

            $this->migrator->setConnection($connection);
            $tenants = $this->selectedTenants($defaultConnection, $tenantTableExists);

            $this->tenantTableExistsOrReport($tenantTableExists);
        }

        if ($this->shouldRunAgainstLandlord()) {
            $this->components->info('Running rollback migrations for landlord database.');

            $this->migrator->setConnection($defaultConnection);

            $this->rollback();
        }

        if ($this->shouldRunAgainstTenant()) {
            if ($tenantTableExists && $this->tenantsAvailableOrReport($tenants)) {
                $this->runForEachTenant($tenants, 'rollback migrations', function (): void {
                    $this->rollback();
                });
            }
        }

        if ($this->shouldRunAgainstLandlord() && $tenantTableExistedBefore && ! $this->hasTenantTable($defaultConnection)) {
            $this->dropTenantDatabases($tenantDatabases);
        }

        $this->migrator->setConnection($defaultConnection);

        return self::SUCCESS;
    }

    /**
     * @throws Throwable
     */
    protected function rollback(): int
    {
        try {
            $this->migrator->setOutput($this->output)->rollback(
                $this->getMigrationPaths(),
                [
                    'pretend' => $this->option('pretend'),
                    'step' => (int) $this->option('step'),
                    'batch' => (int) $this->option('batch'),
                ]
            );
        } catch (Throwable $e) {
            if ($this->option('graceful')) {
                $this->components->warn($e->getMessage());

                return self::SUCCESS;
            }

            throw $e;
        }

        return self::SUCCESS;
    }
}
