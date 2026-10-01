<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

/**
 * Shares a token's request budget (SendSeven allows 100 standard requests a
 * minute per token) between everything that uses it. Implementations may
 * block until capacity is free, or throw.
 */
interface RateLimiter
{
    /**
     * Called before each attempt.
     */
    public function acquire(Request $request): void;

    /**
     * Called with every response, so the limiter can learn from rate-limit
     * headers and 429s.
     */
    public function observe(Request $request, Response $response): void;
}
