<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

/**
 * Applies no limit of its own; 429s are still retried by the retry policy.
 */
final readonly class UnlimitedRateLimiter implements RateLimiter
{
    public function acquire(Request $request): void {}

    public function observe(Request $request, Response $response): void {}
}
