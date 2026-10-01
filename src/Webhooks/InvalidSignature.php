<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks;

use Reshapify\SendSeven\Exceptions\SendSevenException;
use RuntimeException;

/**
 * A webhook delivery failed verification. Respond with 401 and don't act on it.
 */
final class InvalidSignature extends RuntimeException implements SendSevenException
{
    public static function missingHeaders(): self
    {
        return new self('The webhook has no X-SendSeven-Signature or X-SendSeven-Timestamp header. Only the verification challenge is unsigned: check it with Webhook::isVerificationChallenge() first.');
    }

    public static function stale(int $ageSeconds, int $toleranceSeconds): self
    {
        return new self("The webhook's timestamp is {$ageSeconds} seconds from now, beyond the {$toleranceSeconds}-second tolerance, so it may be a replay. Check the server clock if this keeps happening.");
    }

    public static function mismatch(): self
    {
        return new self('The webhook signature does not match. Check the webhook secret (it is shown once, when the endpoint is created) and that the body is verified exactly as received, before any JSON parsing.');
    }

    public static function wrongAuthorization(): self
    {
        return new self('The webhook\'s Authorization header does not match the value configured for the endpoint.');
    }

    public static function malformedBody(): self
    {
        return new self('The webhook body is not a JSON object with an "id" and a "type".');
    }
}
