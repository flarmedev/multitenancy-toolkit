<?php

namespace Flarme\MultitenancyToolkit\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConsumeTenantImpersonationController extends Controller
{
    public function __invoke(Request $request, string $tenant, string $user): RedirectResponse
    {
        $resolvedTenant = $this->resolveTenant($tenant);
        $this->ensureTenantContext($resolvedTenant);
        $this->loginAsImpersonatedUser($this->resolveGuard($request), $user);

        return redirect()->to($this->resolveRedirect($request));
    }

    protected function resolveTenant(string $tenantId): IsTenant
    {
        $tenantModel = config('multitenancy.tenant_model', Tenant::class);

        if (! is_subclass_of($tenantModel, Model::class) || ! is_a($tenantModel, IsTenant::class, true)) {
            abort(403, 'Invalid tenant model provided for impersonation.');
        }

        $tenant = $tenantModel::query()->find($tenantId);

        if (! $tenant instanceof IsTenant) {
            abort(403, 'Unable to resolve tenant for impersonation.');
        }

        return $tenant;
    }

    protected function ensureTenantContext(IsTenant $tenant): void
    {
        $tenantModel = get_class($tenant);
        $currentTenant = $tenantModel::current();
        $currentTenantKey = $currentTenant instanceof Model ? (string) $currentTenant->getKey() : null;
        $tenantKey = $tenant instanceof Model ? (string) $tenant->getKey() : null;

        if ($currentTenantKey !== null && $currentTenantKey !== $tenantKey) {
            abort(403, 'Impersonation link does not match the current tenant.');
        }

        if (! $currentTenant) {
            $tenant->makeCurrent();
        }
    }

    protected function loginAsImpersonatedUser(string $guardName, string $userId): void
    {
        $guard = Auth::guard($guardName);

        if (! method_exists($guard, 'loginUsingId')) {
            throw new HttpException(500, "Guard [{$guardName}] does not support loginUsingId.");
        }

        $result = $guard->loginUsingId($userId);

        if (! $result) {
            abort(403, 'Unable to impersonate the requested user.');
        }
    }

    protected function resolveGuard(Request $request): string
    {
        $guard = $request->query('guard');

        if (! is_string($guard) || $guard === '') {
            $guard = config('multitenancy-toolkit.impersonation.guard') ?: config('auth.defaults.guard', 'web');
        }

        return is_string($guard) && $guard !== '' ? $guard : 'web';
    }

    protected function resolveRedirect(Request $request): string
    {
        $redirect = $request->query('redirect', '/');

        return is_string($redirect) && $redirect !== '' ? $redirect : '/';
    }
}
