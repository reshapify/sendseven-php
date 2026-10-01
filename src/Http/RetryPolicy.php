<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

/**
 * When and how long to wait before trying a request again.
 *
 * Rate-limited (429) and server-error (5xx) responses are retried, as are
 * connection failures. A request is only retried when repeating it is safe:
 * GET, PUT and DELETE always, POST and PATCH only with an Idempotency-Key.
 */
final readonly class RetryPolicy
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMilliseconds = 500,
        public int $maxDelayMilliseconds = 20_000,
    ) {}

    public static function none(): self
    {
        return new self(maxAttempts: 1);
    }

    public function shouldRetry(Request $request, ?Response $response, int $attempt): bool
    {
        if ($attempt >= $this->maxAttempts || ! $this->isSafeToRepeat($request)) {
            return false;
        }

        return ! $response instanceof Response || $response->status === 429 || $response->status >= 500;
    }

    /**
     * Honours Retry-After when SendSeven sends it, otherwise backs off
     * exponentially with jitter so many clients don't retry in step.
     */
    public function delayMilliseconds(int $attempt, ?Response $response): int
    {
        $retryAfter = $response?->header('retry-after');

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return min((int) ((float) $retryAfter * 1000), $this->maxDelayMilliseconds);
        }

        $exponential = $this->baseDelayMilliseconds * (2 ** ($attempt - 1));

        return min($exponential + random_int(0, $this->baseDelayMilliseconds), $this->maxDelayMilliseconds);
    }

    private function isSafeToRepeat(Request $request): bool
    {
        return $request->method->isIdempotent() || $request->header('Idempotency-Key') !== null;
    }
}
