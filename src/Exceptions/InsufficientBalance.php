<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 402: the account's prepaid balance (for example the RCS wallet) is too low.
 */
final class InsufficientBalance extends ApiException
{
    public function hint(): string
    {
        return 'Top up the SendSeven balance the message is paid from, then retry.';
    }
}
