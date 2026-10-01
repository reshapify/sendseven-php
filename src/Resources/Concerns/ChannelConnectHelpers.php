<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Resources\Concerns;

use Reshapify\SendSeven\Data\ChannelConnectTokenCreated;
use Reshapify\SendSeven\Exceptions\ApiException;
use Reshapify\SendSeven\Onboarding\ConnectLink;

/**
 * @internal mixed into ChannelConnect
 */
trait ChannelConnectHelpers
{
    /**
     * Create a connect link from a ConnectLink description.
     *
     * @throws ApiException
     */
    public function create(ConnectLink $link, ?string $idempotencyKey = null): ChannelConnectTokenCreated
    {
        return $this->createToken(
            allowedChannelTypes: $link->channelTypes,
            name: $link->name,
            expiresInHours: $link->expiresInHours,
            maxUses: $link->maxUses,
            useWindowMinutes: $link->useWindowMinutes,
            allowedChannelModes: $link->modes === [] ? null : $link->modes,
            partnerName: $link->partnerName,
            partnerRedirectUrl: $link->redirectUrl,
            idempotencyKey: $idempotencyKey,
        );
    }
}
