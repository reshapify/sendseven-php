<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * An error status the SDK doesn't recognise.
 */
final class UnexpectedStatus extends ApiException
{
    public function hint(): string
    {
        return 'This status is not documented for this endpoint. Check the response body, and report it if it persists.';
    }
}
