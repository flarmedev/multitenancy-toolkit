<?php

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Tenant;

if (! function_exists('tenant')) {
    function tenant(string | int | null $id = null): ?IsTenant
    {
        $tenantModel = config('multitenancy.tenant_model', Tenant::class);

        if (! is_string($tenantModel)) {
            return null;
        }

        if (! is_subclass_of($tenantModel, Model::class) || ! is_a($tenantModel, IsTenant::class, true)) {
            return null;
        }

        if ($id === null) {
            return $tenantModel::current();
        }

        $tenant = $tenantModel::query()->find($id);

        return $tenant instanceof IsTenant ? $tenant : null;
    }
}
