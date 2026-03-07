<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations;

use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\HandlesTenantCommands;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\ResolvesMigrationPaths;
use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\MigrateCommand as BaseCommand;
use Throwable;

class MigrateCommand extends BaseCommand
{
    use HandlesTenantCommands;
    use ResolvesMigrationPaths;

    /**
     * The migrator instance.
     *
     * @var Migrator
     */
    protected $migrator;

    protected $signature = 'tenancy:migrate {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--pretend : Dump the SQL queries that would be run}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}
                {--graceful : Return a successful exit code even if an error occurs}
                {--landlord : Run migrations only for the landlord database}
                {--tenants : Run migrations only for all tenant databases}
                {--tenant= : Run migrations only for the given tenant id}';

    public function __construct(Migrator $migrator, Dispatcher $dispatcher)
    {
        parent::__construct($migrator, $dispatcher);
    }

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if ($this->shouldDelegate()) {
            $this->components->info('Defaulting to Laravel default migrations.');

            return parent::handle();
        }

        if (! $this->ensureScopeOptionsAreValid()) {
            return self::FAILURE;
        }

        $defaultConnection = $this->resolveLandlordConnectionOrFail();
        if (! $defaultConnection) {
            return self::FAILURE;
        }

        $tenantTableExists = true;

        if ($this->shouldRunAgainstLandlord()) {
            $this->components->info('Running migrations for landlord database.');

            $this->migrator->setConnection($defaultConnection);

            $this->migrate();
        }

        if ($this->shouldRunAgainstTenant()) {
            $connection = $this->resolveTenantConnectionOrFail();
            if (! $connection) {
                return self::FAILURE;
            }

            $this->migrator->setConnection($connection);
            $tenants = $this->selectedTenants($defaultConnection, $tenantTableExists);

            if ($this->tenantTableExistsOrReport($tenantTableExists) && $this->tenantsAvailableOrReport($tenants)) {
                $this->runForEachTenant($tenants, 'migrations', function (): void {
                    $this->migrate();
                });
            }
        }

        $this->migrator->setConnection($defaultConnection);

        return self::SUCCESS;
    }

    /**
     * @throws Throwable
     */
    protected function migrate(): int
    {
        try {
            $this->runMigrations();
        } catch (Throwable $e) {
            if ($this->option('graceful')) {
                $this->components->warn($e->getMessage());

                return 0;
            }

            throw $e;
        }

        return 0;
    }
}
