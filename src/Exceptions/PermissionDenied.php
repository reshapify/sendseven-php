<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 403: the token lacks a scope this endpoint needs, or the action isn't allowed for this user.
 */
final class PermissionDenied extends ApiException
{
    public function hint(): string
    {
        return 'Give the token the scope this endpoint needs (see the method\'s docs), or use a token from a user with the right role.';
    }
}
