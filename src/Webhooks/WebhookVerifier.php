<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks;

use SensitiveParameter;

/**
 * Checks that a delivery really came from SendSeven, and recently.
 *
 * SendSeven signs "{X-SendSeven-Timestamp}.{raw body}" with HMAC-SHA256 using
 * the endpoint's secret and sends "sha256=<hex>" in X-SendSeven-Signature.
 * Signing the timestamp is what stops an old delivery from being replayed.
 * Optionally, the static Authorization header the endpoint was registered
 * with is checked as a second, independent gate.
 */
final readonly class WebhookVerifier
{
    public const int DEFAULT_TOLERANCE_SECONDS = 300;

    public function __construct(
        #[SensitiveParameter] private string $secret,
        private int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        #[SensitiveParameter] private ?string $authorization = null,
    ) {}

    /**
     * @param  array<string, string|list<string>>  $headers  any letter case
     * @param  int|null  $now  Unix time; defaults to the current time
     *
     * @throws InvalidSignature
     */
    public function verify(string $body, array $headers, ?int $now = null): void
    {
        $headers = Headers::normalize($headers);

        if ($this->authorization !== null && ! hash_equals($this->authorization, $headers['authorization'] ?? '')) {
            throw InvalidSignature::wrongAuthorization();
        }

        $signature = $headers['x-sendseven-signature'] ?? '';
        $timestamp = $headers['x-sendseven-timestamp'] ?? '';

        if ($signature === '' || $timestamp === '' || preg_match('/^\d+$/', $timestamp) !== 1) {
            throw InvalidSignature::missingHeaders();
        }

        $age = ($now ?? time()) - (int) $timestamp;

        if ($this->toleranceSeconds > 0 && abs($age) > $this->toleranceSeconds) {
            throw InvalidSignature::stale($age, $this->toleranceSeconds);
        }

        if (! hash_equals(self::signature($this->secret, $timestamp, $body), $signature)) {
            throw InvalidSignature::mismatch();
        }
    }

    /**
     * @param  array<string, string|list<string>>  $headers
     */
    public function isValid(string $body, array $headers, ?int $now = null): bool
    {
        try {
            $this->verify($body, $headers, $now);

            return true;
        } catch (InvalidSignature) {
            return false;
        }
    }

    /**
     * "sha256=<hex>" for a body and timestamp, as SendSeven computes it.
     */
    public static function signature(#[SensitiveParameter] string $secret, string $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
