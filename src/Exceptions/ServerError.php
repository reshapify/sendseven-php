<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 5xx: SendSeven failed. Retried automatically before this is thrown.
 */
final class ServerError extends ApiException
{
    public function hint(): string
    {
        return 'This is on SendSeven\'s side and was already retried. Try again later, and quote the request ID to SendSeven support if it persists.';
    }
}
