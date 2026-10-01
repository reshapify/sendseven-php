<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 403: creating tenants (sub-accounts) needs the billing account's owner,
 * and this token belongs to someone else.
 */
final class NotBillingAccountOwner extends ApiException
{
    public function hint(): string
    {
        return 'Use an API token created by the owner of the SendSeven billing account the tenant should belong to.';
    }
}
