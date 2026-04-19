<?php

namespace Flarme\MultitenancyToolkit\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use LogicException;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\DomainTenantFinder;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

trait ImpersonatesUsers
{
    abstract public function execute(callable $callable): mixed;

    public function impersonate(
        string | int | Authenticatable $user,
        ?string $redirectTo = null,
    ): string {
        $this->ensureImpersonationEnabled();

        $tenantId = $this->resolveTenantId($this->tenantForImpersonation());
        $userId = $this->resolveAuthenticatableId($user);

        /** @var array<string, mixed> $parameters */
        $parameters = [
            'tenant' => $tenantId,
            'user' => $userId,
            'guard' => $this->impersonationGuard(),
        ];

        if ($redirectTo !== null) {
            $parameters['redirect'] = $redirectTo;
        }

        return $this->execute(fn () => $this->buildImpersonationUrl($parameters));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function buildImpersonationUrl(array $parameters): string
    {
        $url = app('url');
        $origin = $this->resolveImpersonationOrigin($this->tenantForImpersonation());

        if ($origin === null) {
            return URL::temporarySignedRoute(
                $this->impersonationRouteName(),
                now()->addSeconds(max(1, (int) config('multitenancy-toolkit.impersonation.ttl', 60))),
                $parameters
            );
        }

        $url->useOrigin($origin);

        try {
            return URL::temporarySignedRoute(
                $this->impersonationRouteName(),
                now()->addSeconds(max(1, (int) config('multitenancy-toolkit.impersonation.ttl', 60))),
                $parameters
            );
        } finally {
            $url->useOrigin(null);
        }
    }

    protected function resolveImpersonationOrigin(IsTenant $tenant): ?string
    {
        $request = app()->bound('request') && app('request') instanceof Request
            ? app('request')
            : null;

        if ($request instanceof Request && $this->requestMatchesTenant($request, $tenant)) {
            $origin = $request->root();

            return is_string($origin) && $origin !== '' ? $origin : null;
        }

        return $this->resolveOriginFromTenantFinder($tenant, $request);
    }

    protected function requestMatchesTenant(Request $request, IsTenant $tenant): bool
    {
        if (! $tenant instanceof Model) {
            return false;
        }

        $tenantFinderClass = config('multitenancy.tenant_finder');

        if (! is_string($tenantFinderClass) || $tenantFinderClass === '') {
            return false;
        }

        $tenantFinder = app($tenantFinderClass);

        if (! $tenantFinder instanceof TenantFinder) {
            return false;
        }

        $resolvedTenant = $tenantFinder->findForRequest($request);

        if (! $resolvedTenant instanceof Model) {
            return false;
        }

        return (string) $resolvedTenant->getKey() === (string) $tenant->getKey();
    }

    protected function resolveOriginFromTenantFinder(IsTenant $tenant, ?Request $request): ?string
    {
        $tenantFinderClass = config('multitenancy.tenant_finder');

        if ($tenantFinderClass !== DomainTenantFinder::class) {
            return null;
        }

        if (! $tenant instanceof Model) {
            return null;
        }

        $domain = $tenant->getAttribute('domain');

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        $scheme = $request?->getScheme();

        if (! is_string($scheme) || $scheme === '') {
            $scheme = 'https';
        }

        return "{$scheme}://{$domain}";
    }

    protected function ensureImpersonationEnabled(): void
    {
        if (! (bool) config('multitenancy-toolkit.impersonation.enabled', false)) {
            throw new LogicException('Tenant impersonation is not enabled. Set multitenancy-toolkit.impersonation.enabled to true.');
        }
    }

    protected function tenantForImpersonation(): IsTenant
    {
        if (! $this instanceof IsTenant) {
            throw new LogicException('HasTenantImpersonation can only be used on a model implementing Spatie\Multitenancy\Contracts\IsTenant.');
        }

        return $this;
    }

    protected function resolveTenantId(IsTenant $tenant): string
    {
        if (! $tenant instanceof Model) {
            throw new LogicException('Unable to resolve tenant identifier for impersonation.');
        }

        $key = $tenant->getKey();

        if ($key === null || $key === '') {
            throw new LogicException('Unable to resolve tenant identifier for impersonation.');
        }

        return (string) $key;
    }

    protected function resolveAuthenticatableId(string | int | Authenticatable $value): string
    {
        $identifier = $value instanceof Authenticatable
            ? $value->getAuthIdentifier()
            : $value;

        if ($identifier === null || $identifier === '') {
            throw new LogicException('Unable to resolve authentication identifier for impersonation.');
        }

        return (string) $identifier;
    }

    protected function impersonationRouteName(): string
    {
        return (string) config('multitenancy-toolkit.impersonation.route.name', 'multitenancy-toolkit.impersonate');
    }

    protected function impersonationGuard(): string
    {
        $guard = config('multitenancy-toolkit.impersonation.guard') ?: config('auth.defaults.guard', 'web');

        return is_string($guard) && $guard !== '' ? $guard : 'web';
    }
}
