<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 409: the request clashes with the resource's current state, or reuses an idempotency key with a different body.
 */
final class Conflict extends ApiException
{
    public function hint(): string
    {
        return "Fetch the resource's current state and retry, or use a new Idempotency-Key for a different request.";
    }
}
