<?php

namespace Flarme\MultitenancyToolkit\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use LogicException;
use Spatie\Multitenancy\Contracts\IsTenant;

trait ImpersonatesUsers
{
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

        return URL::temporarySignedRoute(
            $this->impersonationRouteName(),
            now()->addSeconds(max(1, (int) config('multitenancy-toolkit.impersonation.ttl', 60))),
            $parameters
        );
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
