<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 403: the account's plan doesn't include the feature this endpoint needs,
 * e.g. "multi_tenant" for creating sub-accounts.
 */
final class FeatureDisabled extends ApiException
{
    /**
     * Plans that include each feature, from sendseven.com/en/pricing.
     */
    private const array PLANS = [
        'multi_tenant' => 'Professional, Scale, Enterprise or API Only',
    ];

    /**
     * The feature SendSeven named, e.g. "multi_tenant".
     */
    public function feature(): ?string
    {
        $feature = $this->detail->extra['feature'] ?? null;

        return is_string($feature) ? $feature : null;
    }

    public function hint(): string
    {
        $feature = $this->feature() ?? 'this feature';
        $plans = self::PLANS[$feature] ?? null;

        return $plans === null
            ? "The account's SendSeven plan doesn't include {$feature}. Check the plan, or that a trial hasn't ended."
            : "The account's SendSeven plan doesn't include {$feature}: it comes with {$plans}. A trial that has ended also loses it.";
    }
}
