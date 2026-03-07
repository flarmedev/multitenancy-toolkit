<?php

use Flarme\MultitenancyToolkit\Http\Controllers\ConsumeTenantImpersonationController;
use Illuminate\Support\Facades\Route;

Route::middleware((array) config('multitenancy-toolkit.impersonation.route.middleware', ['web', 'signed', 'tenant']))
    ->prefix((string) config('multitenancy-toolkit.impersonation.route.prefix', '_multitenancy-toolkit'))
    ->group(function (): void {
        Route::get(
            (string) config('multitenancy-toolkit.impersonation.route.path', 'impersonate/{tenant}/{user}'),
            ConsumeTenantImpersonationController::class
        )
            ->name((string) config(
                'multitenancy-toolkit.impersonation.route.name',
                'multitenancy-toolkit.impersonate'
            ));
    });
