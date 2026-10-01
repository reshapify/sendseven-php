<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 401: the token is missing, wrong, expired or revoked.
 */
final class AuthenticationFailed extends ApiException
{
    public function hint(): string
    {
        return 'Check the API token you passed to SendSeven::client(): it may be wrong, expired or revoked. Create a new one under Settings → API Tokens in SendSeven.';
    }
}
