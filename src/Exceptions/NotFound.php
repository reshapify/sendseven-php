<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 404: the resource doesn't exist, or isn't in this tenant.
 */
final class NotFound extends ApiException
{
    public function hint(): string
    {
        return 'Check the ID, and that it belongs to the tenant this token (or forTenant()) points at.';
    }
}
