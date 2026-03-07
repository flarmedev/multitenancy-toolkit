<?php

use Spatie\Multitenancy\Models\Tenant;

describe('tenant helper', function () {
    beforeEach(fn () => instantiate());

    it('returns null when there is no current tenant', function () {
        Tenant::forgetCurrent();

        expect(tenant())->toBeNull();
    });

    it('returns current tenant when no id is provided', function () {
        $currentTenant = makeTenant(['name' => 'current-tenant']);

        $currentTenant->makeCurrent();

        expect(tenant())->not->toBeNull()
            ->and((string) tenant()?->getKey())->toBe((string) $currentTenant->getKey());
    });

    it('returns tenant by id', function () {
        $selected = makeTenant(['name' => 'selected-tenant']);
        makeTenant(['name' => 'another-tenant']);

        expect(tenant($selected->id))->not->toBeNull()
            ->and((string) tenant($selected->id)?->getKey())->toBe((string) $selected->getKey());
    });

    it('returns null when tenant id does not exist', function () {
        makeTenant();

        expect(tenant(999999))->toBeNull();
    });
});
