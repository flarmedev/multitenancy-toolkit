<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

trait HandlesTenantDatabasesDrop
{
    /**
     * @return list<string>
     */
    protected function getTenantDatabaseNames(EloquentCollection $tenants): array
    {
        return (new Collection($tenants->all()))
            ->pluck('database')
            ->filter(fn ($database) => is_string($database) && $database !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $databases
     */
    protected function dropTenantDatabases(array $databases): void
    {
        if ($databases === []) {
            return;
        }

        $tenantConnection = config('multitenancy.tenant_database_connection_name');
        $driver = config("database.connections.{$tenantConnection}.driver");

        if (! $tenantConnection || ! $driver) {
            $this->components->warn('Unable to drop tenant databases because tenant connection is not configured.');

            return;
        }

        foreach ($databases as $database) {
            try {
                if ($driver === 'sqlite') {
                    $this->dropSqliteDatabaseFile($database);
                } else {
                    $this->dropServerDatabase($tenantConnection, $driver, $database);
                }
            } catch (Throwable $e) {
                $this->components->warn("Failed to drop tenant database {$database}: {$e->getMessage()}");
            }
        }
    }

    protected function dropSqliteDatabaseFile(string $database): void
    {
        if ($database === ':memory:' || str_contains($database, '?mode=memory') || str_contains($database, '&mode=memory')) {
            return;
        }

        $path = $database;

        if (str_starts_with($database, 'file:')) {
            $path = explode('?', substr($database, 5), 2)[0];
        }

        if ($path !== '' && File::exists($path)) {
            File::delete($path);
        }
    }

    protected function dropServerDatabase(string $tenantConnection, string $driver, string $database): void
    {
        $connection = "__tenant_dropper_{$tenantConnection}";
        $config = config("database.connections.{$tenantConnection}");

        if (! is_array($config)) {
            return;
        }

        if ($driver === 'pgsql') {
            $config['database'] = $config['maintenance_database'] ?? 'postgres';
        } elseif ($driver === 'sqlsrv') {
            $config['database'] = $config['maintenance_database'] ?? 'master';
        } else {
            $config['database'] = $config['maintenance_database'] ?? 'information_schema';
        }

        config(["database.connections.{$connection}" => $config]);

        try {
            DB::purge($connection);
            Schema::connection($connection)->dropDatabaseIfExists($database);
        } finally {
            DB::disconnect($connection);
            DB::purge($connection);

            $connections = config('database.connections', []);
            if (is_array($connections) && array_key_exists($connection, $connections)) {
                unset($connections[$connection]);
                config(['database.connections' => $connections]);
            }
        }
    }
}
