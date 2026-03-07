# Multitenancy Toolkit

`flarme/multitenancy-toolkit` is an extension package for [`spatie/laravel-multitenancy`](https://github.com/spatie/laravel-multitenancy).

It adds practical tooling for:
- landlord/tenant scoped migration commands
- migration path registration helpers for package/service providers
- optional signed-URL tenant impersonation
- a preconfigured `tenant` middleware group

## Requirements

- PHP and Laravel versions supported by `spatie/laravel-multitenancy:^4.0`
- `spatie/laravel-multitenancy:^4.0`

## Installation

```bash
composer require flarme/multitenancy-toolkit
```

The package provider is auto-discovered.

## Package Structure

- `src/MultitenancyToolkitProvider.php`: package bootstrapping
- `src/ServiceProvider.php`: migration-loading helper base provider for your modules/packages
- `src/Console/Commands/Migrations/*`: migration command overrides (`migrate`, `migrate:fresh`, `migrate:rollback`)
- `src/Database/Migrations/Migrator.php`: migrator with landlord/tenant path support
- `src/Concerns/ImpersonatesUsers.php`: tenant model concern for impersonation links
- `src/Http/Controllers/ConsumeTenantImpersonationController.php`: signed impersonation link consumer
- `config/multitenancy-toolkit.php`: package configuration
- `routes/impersonation.php`: impersonation route registration

## Configuration

Publish configuration:

```bash
php artisan vendor:publish --tag=multitenancy-toolkit-config
```

Main config file: `config/multitenancy-toolkit.php`

### Options

- `register_migrations_directories` (`bool`, default: `true`)
  - auto-registers `database/migrations/landlord` and `database/migrations/tenant`
- `tenant_middlewares` (`array`)
  - middleware list assigned to middleware group name `tenant`
- `impersonation.enabled` (`bool`, default: `false`)
  - enables signed impersonation route loading
- `impersonation.ttl` (`int`, default: `60`)
  - signed URL lifetime in seconds
- `impersonation.guard` (`string|null`, default: `null`)
  - auth guard used during impersonation
- `impersonation.route.*`
  - route middleware/prefix/path/name customization

## Migration Commands

This package replaces Laravel migration commands with tenant-aware variants.

### Supported commands

- `php artisan migrate`
- `php artisan migrate:fresh`
- `php artisan migrate:rollback`

### Scope options

- `--landlord`: run only landlord migrations
- `--tenants`: run only tenant migrations for all tenants
- `--tenant={id}`: run only tenant migrations for one tenant

Rules:
- only one scope flag can be used at once
- if no scope flag is given, landlord + tenants are run
- `--database` and `--path` delegate to default Laravel command behavior

### Graceful mode

For `migrate` and `migrate:rollback`, use:

```bash
php artisan migrate --tenants --graceful
php artisan migrate:rollback --tenants --graceful
```

This returns success even when a tenant operation throws.

## Registering Migration Paths in Your Package/Module

Extend `Flarme\MultitenancyToolkit\ServiceProvider` in your own service provider:

```php
<?php

namespace App\Providers;

use Flarme\MultitenancyToolkit\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadLandlordMigrationsFrom(database_path('migrations/billing/landlord'));
        $this->loadTenantMigrationsFrom(database_path('migrations/billing/tenant'));
    }
}
```

## Tenant Middleware Group

At boot, the package registers a middleware group named `tenant` from `multitenancy-toolkit.tenant_middlewares`.

Example:

```php
Route::middleware('tenant')->group(function () {
    // tenant-only routes
});
```

## Global Helper

The package ships a `tenant(?$id = null)` helper.

- `tenant()` returns the current tenant (or `null`)
- `tenant($id)` returns the tenant model for that id (or `null`)

Example:

```php
$current = tenant();
$byId = tenant(12);
```

## Impersonation (Optional)

Impersonation is disabled by default.

1. Enable it in `config/multitenancy-toolkit.php`:

```php
'impersonation' => [
    'enabled' => true,
    // ...
],
```

2. Add `ImpersonatesUsers` to your tenant model:

```php
<?php

namespace App\Models;

use Flarme\MultitenancyToolkit\Concerns\ImpersonatesUsers;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use ImpersonatesUsers;
}
```

3. Generate signed impersonation URLs:

```php
$url = $tenant->impersonate($userIdOrUserModel, '/dashboard');
```

The URL is signed, temporary, and consumed through the package route.

## Development

```bash
composer install
./vendor/bin/pest
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=1G
```

## Testing

Current test suites:
- `tests/Feature`

Run all tests:

```bash
./vendor/bin/pest
```

## License

MIT
