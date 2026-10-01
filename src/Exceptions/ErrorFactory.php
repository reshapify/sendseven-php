<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;

/**
 * Turns an error response into the exception that says what went wrong.
 *
 * @internal
 */
final class ErrorFactory
{
    public static function make(Request $request, Response $response): ApiException
    {
        $detail = ErrorDetail::from($response);

        $class = match (true) {
            $response->status === 401 => AuthenticationFailed::class,
            $response->status === 402 || str_starts_with((string) $detail->code, 'insufficient_') => InsufficientBalance::class,
            $response->status === 403 && $detail->code === 'feature_disabled' => FeatureDisabled::class,
            $response->status === 403 && stripos($detail->message, 'billing account owner') !== false => NotBillingAccountOwner::class,
            $response->status === 403 => PermissionDenied::class,
            $response->status === 404 => NotFound::class,
            $response->status === 409 => Conflict::class,
            $response->status === 422 => ValidationFailed::class,
            $response->status === 429 => RateLimited::class,
            $response->status >= 500 => ServerError::class,
            default => UnexpectedStatus::class,
        };

        return new $class($request, $response, $detail);
    }
}
