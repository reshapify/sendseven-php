<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

/**
 * 429: the token used up its request budget (100 standard requests a minute)
 * and the retries ran out.
 */
final class RateLimited extends ApiException
{
    /**
     * Seconds SendSeven asked us to wait, when it said.
     */
    public function retryAfter(): ?int
    {
        $value = $this->response->header('retry-after');

        return $value !== null && is_numeric($value) ? (int) ceil((float) $value) : null;
    }

    public function hint(): string
    {
        $wait = $this->retryAfter();

        return ($wait === null ? 'Slow down and retry shortly' : "Retry after {$wait} seconds")
            .'. If several processes share this token, give them a shared RateLimiter.';
    }
}
