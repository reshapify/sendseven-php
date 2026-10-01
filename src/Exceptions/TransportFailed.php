<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Reshapify\SendSeven\Http\Request;
use RuntimeException;
use Throwable;

/**
 * No response arrived: DNS, TLS, a refused connection or a timeout.
 * Retried automatically where safe before this is thrown.
 */
final class TransportFailed extends RuntimeException implements SendSevenException
{
    private function __construct(string $message, public readonly Request $request, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function for(Request $request, Throwable $previous): self
    {
        return new self("Could not reach SendSeven for {$request->describe()}: {$previous->getMessage()}. Check the network and the base URI.", $request, $previous);
    }
}
