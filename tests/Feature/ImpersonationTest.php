<?php

use Tests\Feature\Fixtures\Models\ImpersonatingTenant;

it('stays fully disabled by default', function () {
    expect(app('router')->has('multitenancy-toolkit.impersonate'))->toBeFalse();
});

it('throws a clear error when using tenant trait while impersonation is disabled', function () {
    $tenant = new ImpersonatingTenant;

    expect(fn () => $tenant->impersonate(1))
        ->toThrow(\LogicException::class, 'Tenant impersonation is not enabled.');
});
