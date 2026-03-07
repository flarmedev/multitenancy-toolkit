<?php

namespace Tests\Feature\Fixtures\Models;

use Flarme\MultitenancyToolkit\Concerns\ImpersonatesUsers;
use Spatie\Multitenancy\Models\Tenant;

class ImpersonatingTenant extends Tenant
{
    use ImpersonatesUsers;

    protected $table = 'tenants';

    protected $guarded = [];
}
