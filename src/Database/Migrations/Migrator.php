<?php

namespace Flarme\MultitenancyToolkit\Database\Migrations;

use Illuminate\Database\Migrations\Migrator as BaseMigrator;

class Migrator extends BaseMigrator
{
    /** @var list<string> */
    protected array $landlordPaths = [];

    /** @var list<string> */
    protected array $tenantPaths = [];

    public function landlordPath(string $path): void
    {
        $this->landlordPaths[] = $path;
    }

    public function tenantPath(string $path): void
    {
        $this->tenantPaths[] = $path;
    }

    /** @return list<string> */
    public function landlordPaths(): array
    {
        return $this->landlordPaths;
    }

    /** @return list<string> */
    public function tenantPaths(): array
    {
        return $this->tenantPaths;
    }
}
