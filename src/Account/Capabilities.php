<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Account;

use Reshapify\SendSeven\Data\FeatureAvailability;
use Reshapify\SendSeven\Data\MyScopes;
use Reshapify\SendSeven\Data\Tenant;

/**
 * What the token can do: its tenant, the plan's features and the token's
 * scopes. Check it before relying on partner features (sub-accounts).
 *
 *     $capabilities = $sendseven->capabilities();
 *
 *     if (! $capabilities->canCreateTenants()) {
 *         echo $capabilities->whyNotCreateTenants();
 *     }
 */
final readonly class Capabilities
{
    public function __construct(
        /** The tenant the token belongs to, when SendSeven lists it. */
        public ?Tenant $tenant,
        public FeatureAvailability $features,
        public MyScopes $scopes,
    ) {}

    public function tenantId(): string
    {
        return $this->scopes->tenantId;
    }

    /**
     * The plan's package, e.g. "API_ONLY".
     */
    public function package(): string
    {
        return $this->features->packageType;
    }

    /**
     * Multi-tenant management: creating and controlling sub-accounts. Comes
     * with Professional, Scale, Enterprise and API Only.
     */
    public function hasMultiTenant(): bool
    {
        return ($this->features->uiFeatures['multi_tenant'] ?? false) === true
            && ! in_array('multi_tenant', $this->features->disabledFeatures, true);
    }

    public function sendsSms(): bool
    {
        return $this->tenant?->smsEnabled === true;
    }

    public function sendsRcs(): bool
    {
        return $this->tenant?->rcsEnabled === true;
    }

    /**
     * Whether the token has a scope, honouring wildcards ("*:*", "contacts:*").
     */
    public function hasScope(string $scope): bool
    {
        [$area] = explode(':', $scope, 2) + [1 => ''];

        foreach ($this->scopes->scopes as $granted) {
            if (in_array($granted, [$scope, '*:*', '*', $area.':*'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether creating tenants can work. SendSeven also requires the token to
     * belong to the billing account's owner, which the API can't tell us;
     * a refusal for that reason throws NotBillingAccountOwner.
     */
    public function canCreateTenants(): bool
    {
        return $this->hasMultiTenant() && $this->hasScope('tenants:create');
    }

    /**
     * Whether forTenant() (X-Tenant-ID) can act on other tenants.
     */
    public function canManageTenants(): bool
    {
        return $this->hasMultiTenant() && $this->hasScope('tenants:manage');
    }

    /**
     * Why creating tenants won't work, in a sentence; null when it should.
     */
    public function whyNotCreateTenants(): ?string
    {
        return match (true) {
            ! $this->hasMultiTenant() => "This SendSeven plan ({$this->features->packageType}) doesn't include multi-tenant management. It comes with Professional, Scale, Enterprise and API Only; a trial that has ended loses it.",
            ! $this->hasScope('tenants:create') => 'The token lacks the tenants:create scope.',
            default => null,
        };
    }
}
