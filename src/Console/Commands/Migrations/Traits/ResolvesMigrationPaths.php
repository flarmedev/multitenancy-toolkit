<?php

namespace Flarme\MultitenancyToolkit\Console\Commands\Migrations\Traits;

use Illuminate\Support\Collection;

trait ResolvesMigrationPaths
{
    protected function getMigrationPaths(): array
    {
        if ($this->input->hasOption('path') && $this->option('path')) {
            return (new Collection($this->option('path')))->map(function ($path) {
                return ! $this->usingRealPath()
                    ? $this->laravel->basePath() . '/' . $path
                    : $path;
            })->all();
        }

        $paths = array_merge($this->migrator->paths(), [$this->getMigrationPath()]);

        if (! $this->usesMultipleDatabasesSetup()) {
            return array_merge($paths, $this->migrator->tenantPaths(), $this->migrator->landlordPaths());
        }

        return array_merge(
            $paths,
            $this->checkTenant()
                ? $this->migrator->tenantPaths()
                : $this->migrator->landlordPaths()
        );
    }
}
