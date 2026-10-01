<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Reshapify\SendSeven\Http\Response;
use RuntimeException;
use Throwable;

/**
 * SendSeven answered successfully, but not in the shape the SDK expects:
 * usually an API change. The raw response is attached.
 */
final class UnexpectedResponse extends RuntimeException implements SendSevenException
{
    private function __construct(string $message, public readonly ?Response $response, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function because(string $reason, ?Response $response = null, ?Throwable $previous = null): self
    {
        return new self("SendSeven's response was not in the expected shape: {$reason}. If SendSeven changed its API, please report it to reshapify/sendseven-php.", $response, $previous);
    }
}
