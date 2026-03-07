<?php

namespace Tests\Impersonation;

use Tests\Feature\Fixtures\Models\ImpersonatedUser;
use Tests\Feature\Fixtures\Models\ImpersonatingTenant;
use Tests\TestCase;

abstract class ImpersonationEnabledTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function multitenancyToolkitConfig(): array
    {
        return [
            'multitenancy-toolkit.impersonation.enabled' => true,
            'multitenancy.tenant_model' => ImpersonatingTenant::class,
            'auth.providers.users.model' => ImpersonatedUser::class,
            'auth.guards.web.provider' => 'users',
        ];
    }
}
