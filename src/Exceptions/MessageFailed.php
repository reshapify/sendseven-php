<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use RuntimeException;

/**
 * SendSeven accepted the request but reported the message as failed, e.g.
 * outside WhatsApp's 24-hour window. The request itself succeeded, so this
 * is not an ApiException; retrying the same message won't help.
 */
final class MessageFailed extends RuntimeException implements SendSevenException
{
    private function __construct(string $message, public readonly string $messageId, public readonly ?string $reason, public readonly ?string $errorCode)
    {
        parent::__construct($message);
    }

    public static function sending(string $messageId, ?string $reason, int|string|null $errorCode = null): self
    {
        $errorCode = $errorCode === null ? null : (string) $errorCode;
        $why = $reason ?? 'no reason given';

        return new self(
            "SendSeven reported message {$messageId} as failed ({$why}".($errorCode === null ? '' : ", code {$errorCode}").'). Check the recipient and, on WhatsApp, that they messaged you in the last 24 hours or that you sent an approved template.',
            $messageId,
            $reason,
            $errorCode,
        );
    }
}
