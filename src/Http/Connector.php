<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

use Reshapify\SendSeven\Exceptions\ApiException;
use Reshapify\SendSeven\Exceptions\ErrorFactory;
use Reshapify\SendSeven\Exceptions\TransportFailed;
use Reshapify\SendSeven\SendSeven;

/**
 * Sends a request the way every SendSeven call should go: authenticated,
 * scoped to the right tenant, idempotent where it writes, within the rate
 * limit, retried when it is safe, and with errors turned into exceptions.
 *
 * @internal resources talk to this; applications use the Client
 */
final readonly class Connector
{
    public function __construct(
        private Transport $transport,
        private string $token,
        private ?string $tenantId = null,
        private RetryPolicy $retryPolicy = new RetryPolicy,
        private RateLimiter $rateLimiter = new UnlimitedRateLimiter,
        private Sleeper $sleeper = new SystemSleeper,
        private bool $automaticIdempotencyKeys = true,
    ) {}

    /**
     * The same connector acting on another tenant (X-Tenant-ID), for
     * partner tokens with the tenants:manage grant.
     */
    public function forTenant(?string $tenantId): self
    {
        return new self($this->transport, $this->token, $tenantId, $this->retryPolicy, $this->rateLimiter, $this->sleeper, $this->automaticIdempotencyKeys);
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * @throws ApiException when SendSeven answers with an error
     * @throws TransportFailed when SendSeven can't be reached
     */
    public function send(Request $request): Response
    {
        $request = $this->prepare($request);
        $attempt = 0;

        while (true) {
            $attempt++;
            $this->rateLimiter->acquire($request);

            try {
                $response = $this->transport->send($request);
            } catch (TransportFailed $exception) {
                if (! $this->retryPolicy->shouldRetry($request, null, $attempt)) {
                    throw $exception;
                }

                $this->sleeper->sleep($this->retryPolicy->delayMilliseconds($attempt, null));

                continue;
            }

            $this->rateLimiter->observe($request, $response);

            if ($response->successful()) {
                return $response;
            }

            if (! $this->retryPolicy->shouldRetry($request, $response, $attempt)) {
                throw ErrorFactory::make($request, $response);
            }

            $this->sleeper->sleep($this->retryPolicy->delayMilliseconds($attempt, $response));
        }
    }

    private function prepare(Request $request): Request
    {
        $prepared = $request
            ->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'reshapify/sendseven-php/'.SendSeven::VERSION.' PHP/'.PHP_VERSION);

        if ($this->tenantId !== null && $request->header('X-Tenant-ID') === null) {
            $prepared = $prepared->withHeader('X-Tenant-ID', $this->tenantId);
        }

        // A key per logical request makes POST and PATCH safe to retry: SendSeven
        // answers a repeat with the original result instead of acting twice.
        if ($this->automaticIdempotencyKeys && ! $request->method->isIdempotent() && $request->header('Idempotency-Key') === null) {
            return $prepared->withHeader('Idempotency-Key', $this->uuid());
        }

        return $prepared;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
