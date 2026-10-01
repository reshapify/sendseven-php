<?php

declare(strict_types=1);

namespace Reshapify\SendSeven;

use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Reshapify\SendSeven\Http\Connector;
use Reshapify\SendSeven\Http\PsrTransport;
use Reshapify\SendSeven\Http\RateLimiter;
use Reshapify\SendSeven\Http\RetryPolicy;
use Reshapify\SendSeven\Http\Sleeper;
use Reshapify\SendSeven\Http\SystemSleeper;
use Reshapify\SendSeven\Http\Transport;
use Reshapify\SendSeven\Http\UnlimitedRateLimiter;
use SensitiveParameter;

/**
 * Builds a Client. Every setting has a sensible default; only the token is
 * required.
 */
final class Factory
{
    private ?string $token = null;

    private string $baseUri = SendSeven::BASE_URI;

    private ?string $tenantId = null;

    private ?ClientInterface $httpClient = null;

    private ?RequestFactoryInterface $requestFactory = null;

    private ?StreamFactoryInterface $streamFactory = null;

    private ?Transport $transport = null;

    private RetryPolicy $retryPolicy;

    private RateLimiter $rateLimiter;

    private Sleeper $sleeper;

    private bool $automaticIdempotencyKeys = true;

    public function __construct()
    {
        $this->retryPolicy = new RetryPolicy;
        $this->rateLimiter = new UnlimitedRateLimiter;
        $this->sleeper = new SystemSleeper;
    }

    /**
     * The API token (Settings → API Tokens in SendSeven). It belongs to one
     * tenant; everything the client does happens in that tenant unless
     * forTenant() says otherwise.
     */
    public function withToken(#[SensitiveParameter] string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function withBaseUri(string $baseUri): self
    {
        $this->baseUri = $baseUri;

        return $this;
    }

    /**
     * Act on another tenant (sub-account) by default. Needs a partner token:
     * multi-tenant management on the plan and the tenants:manage grant.
     */
    public function withTenant(?string $tenantId): self
    {
        $this->tenantId = $tenantId;

        return $this;
    }

    /**
     * Use a specific PSR-18 client (and, optionally, PSR-17 factories)
     * instead of the one discovered automatically.
     */
    public function withHttpClient(ClientInterface $client, ?RequestFactoryInterface $requestFactory = null, ?StreamFactoryInterface $streamFactory = null): self
    {
        $this->httpClient = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;

        return $this;
    }

    /**
     * Replace the transport entirely, e.g. with a recording or fake one.
     */
    public function withTransport(Transport $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    public function withRetries(int $maxAttempts, int $baseDelayMilliseconds = 500): self
    {
        $this->retryPolicy = new RetryPolicy(max(1, $maxAttempts), $baseDelayMilliseconds);

        return $this;
    }

    public function withoutRetries(): self
    {
        $this->retryPolicy = RetryPolicy::none();

        return $this;
    }

    /**
     * Share the token's request budget across processes.
     */
    public function withRateLimiter(RateLimiter $rateLimiter): self
    {
        $this->rateLimiter = $rateLimiter;

        return $this;
    }

    public function withSleeper(Sleeper $sleeper): self
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /**
     * Stop adding an Idempotency-Key to POST and PATCH requests. Writes are
     * then not retried, since repeating them could act twice.
     */
    public function withoutAutomaticIdempotencyKeys(): self
    {
        $this->automaticIdempotencyKeys = false;

        return $this;
    }

    public function make(): Client
    {
        if ($this->token === null || trim($this->token) === '') {
            throw new InvalidArgumentException('A SendSeven API token is required: SendSeven::factory()->withToken($token)->make().');
        }

        return new Client(new Connector(
            transport: $this->transport ?? new PsrTransport($this->baseUri, $this->httpClient, $this->requestFactory, $this->streamFactory),
            token: $this->token,
            tenantId: $this->tenantId,
            retryPolicy: $this->retryPolicy,
            rateLimiter: $this->rateLimiter,
            sleeper: $this->sleeper,
            automaticIdempotencyKeys: $this->automaticIdempotencyKeys,
        ));
    }
}
