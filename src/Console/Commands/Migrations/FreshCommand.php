<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations;

use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\HandlesTenantCommands;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\HandlesTenantDatabasesDrop;
use Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits\ResolvesMigrationPaths;
use Flarme\MultitenancyToolkit\Database\Migrations\Migrator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Console\Migrations\FreshCommand as BaseCommand;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class FreshCommand extends BaseCommand
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

    protected $signature = 'tenancy:migrate:fresh
                {--database= : The database connection to use}
                {--drop-views : Drop all tables and views}
                {--drop-types : Drop all tables and types (Postgres only)}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
                {--schema-path= : The path to a schema dump file}
                {--seed : Indicates if the seed task should be re-run}
                {--seeder= : The class name of the root seeder}
                {--step : Force the migrations to be run so they can be rolled back individually}
                {--landlord : Run migrations only for the landlord database}
                {--tenants : Run migrations only for all tenant databases}
                {--tenant= : Run migrations only for the given tenant id}';

    public function __construct(Migrator $migrator)
    {
        parent::__construct($migrator);
    }

    public function handle(): int
    {
        if ($this->isProhibited() || ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! $this->usesMultipleDatabasesSetup()) {
            $this->components->info('Running fresh migrations on the default database.');

            $this->fresh($this->option('database'));

            return self::SUCCESS;
        }

        if (
            ($this->hasOption('database') && $this->option('database'))
            || ($this->hasOption('path') && $this->option('path'))
        ) {
            $this->components->info('Defaulting to Laravel default fresh migrations.');

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
        $tenantConnection = null;
        $tenants = new EloquentCollection;
        $tenantTableExists = true;

        if ($this->shouldRunAgainstLandlord()) {
            $tenantTableExistedBefore = $this->hasTenantTable($defaultConnection);

            if ($tenantTableExistedBefore) {
                $tenantDatabases = $this->getTenantDatabaseNames(Tenant::all());
            }
        }

        if ($this->shouldRunAgainstTenant()) {
            $tenantConnection = $this->resolveTenantConnectionOrFail();
            if (! $tenantConnection) {
                return self::FAILURE;
            }

            $tenants = $this->selectedTenants($defaultConnection, $tenantTableExists);
            $this->tenantTableExistsOrReport($tenantTableExists);
        }

        if ($this->shouldRunAgainstLandlord()) {
            $this->components->info('Running fresh migrations for landlord database.');
            $this->fresh($defaultConnection);
        }

        if ($this->shouldRunAgainstTenant()) {
            if ($tenantTableExists && $this->tenantsAvailableOrReport($tenants)) {
                $this->runForEachTenant($tenants, 'fresh migrations', function () use ($tenantConnection): void {
                    $this->fresh($tenantConnection);
                });
            }
        }

        if ($this->shouldRunAgainstLandlord() && $tenantTableExistedBefore && ! $this->hasTenantTable($defaultConnection)) {
            $this->dropTenantDatabases($tenantDatabases);
        }

        return self::SUCCESS;
    }

    protected function fresh(?string $database): int
    {
        $this->migrator->usingConnection($database, function () use ($database): void {
            if ($this->migrator->repositoryExists()) {
                $this->newLine();

                if ($this->shouldUseSqliteDropFallback($database)) {
                    $this->components->task('Dropping all tables', function () use ($database): bool {
                        $this->dropAllSqliteObjectsInTransaction($database);

                        return true;
                    });
                } else {
                    $this->components->task('Dropping all tables', fn () => $this->callSilent('db:wipe', array_filter([
                        '--database' => $database,
                        '--drop-views' => $this->option('drop-views'),
                        '--drop-types' => $this->option('drop-types'),
                        '--force' => true,
                    ])) === SymfonyCommand::SUCCESS);
                }
            }
        });

        $this->newLine();

        $this->call('migrate', array_filter([
            '--database' => $database,
            '--path' => $this->getMigrationPaths(),
            '--realpath' => true,
            '--schema-path' => $this->option('schema-path'),
            '--force' => true,
            '--step' => $this->option('step'),
        ]));

        if ($this->laravel->bound(Dispatcher::class)) {
            $this->laravel[Dispatcher::class]->dispatch(
                new DatabaseRefreshed($database, $this->needsSeeding())
            );
        }

        if ($this->needsSeeding()) {
            $this->runSeeder($database);
        }

        return self::SUCCESS;
    }

    protected function shouldUseSqliteDropFallback(?string $database): bool
    {
        $connection = DB::connection($database);

        return $connection->getDriverName() === 'sqlite' && $connection->transactionLevel() > 0;
    }

    protected function usingRealPath(): bool
    {
        return $this->input->hasOption('realpath') && $this->option('realpath');
    }

    protected function getMigrationPath(): string
    {
        return $this->laravel->databasePath() . DIRECTORY_SEPARATOR . 'migrations';
    }

    protected function dropAllSqliteObjectsInTransaction(?string $database): void
    {
        $connection = DB::connection($database);

        $connection->statement('PRAGMA foreign_keys = OFF');

        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $table) {
            $name = is_array($table) ? $table['name'] : $table->name;
            $connection->statement("DROP TABLE IF EXISTS \"{$name}\"");
        }

        if ($this->option('drop-views')) {
            $views = $connection->select("SELECT name FROM sqlite_master WHERE type='view' AND name NOT LIKE 'sqlite_%'");
            foreach ($views as $view) {
                $name = is_array($view) ? $view['name'] : $view->name;
                $connection->statement("DROP VIEW IF EXISTS \"{$name}\"");
            }
        }

        $connection->statement('PRAGMA foreign_keys = ON');
    }
}
