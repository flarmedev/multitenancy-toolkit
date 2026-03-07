<?php

use Spatie\Multitenancy\Http\Middleware\EnsureValidTenantSession;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;

return [
    /*
     * Will add ./database/migrations/tenant and ./database/migrations/landlord scoped to their context
     */
    'register_migrations_directories' => true,

    'tenant_middlewares' => [
        NeedsTenant::class,
        EnsureValidTenantSession::class,
    ],

    'impersonation' => [
        /*
         * Enable tenant impersonation routes and helpers.
         */
        'enabled' => env('MULTITENANCY_TOOLKIT_IMPERSONATION_ENABLED', false),

        /*
         * Lifetime (in seconds) of generated impersonation URLs.
         */
        'ttl' => env('MULTITENANCY_TOOLKIT_IMPERSONATION_TTL', 60),

        /*
         * Guard used while impersonating.
         */
        'guard' => null,

        'route' => [
            /*
             * Middleware stack for the token consumption endpoint.
             * `web` and `signed` are required for session auth + URL signature validation.
             */
            'middleware' => ['web', 'signed'],

            /*
             * Route prefix and path for token consumption.
             */
            'prefix' => '_multitenancy-toolkit',
            'path' => 'impersonate/{tenant}/{user}',

            /*
             * Route name used to generate impersonation URLs.
             */
            'name' => 'multitenancy-toolkit.impersonate',
        ],
    ],
];
