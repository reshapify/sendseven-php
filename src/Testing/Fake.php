<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Testing;

use Closure;
use PHPUnit\Framework\Assert;
use Reshapify\SendSeven\Client;
use Reshapify\SendSeven\Http\Connector;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\Http\RetryPolicy;

/**
 * A SendSeven that answers from a script and remembers what it was asked.
 * Requests go through the real pipeline (headers, idempotency keys, error
 * mapping), so a scripted 422 throws ValidationFailed exactly as SendSeven
 * would; retries are off, so a scripted failure fails once.
 */
final readonly class Fake
{
    public Client $client;

    private FakeTransport $transport;

    /**
     * @param  array<string|int, mixed>  $responses  see respond()
     */
    public function __construct(array $responses = [])
    {
        $this->transport = new FakeTransport;
        $this->client = new Client(new Connector($this->transport, 'fake-token', retryPolicy: RetryPolicy::none()));
        $this->respond($responses);
    }

    /**
     * Script answers. Keys are "METHOD /path" patterns ("*" matches any
     * segment run, e.g. "GET /contacts/*"); values are a Response, an array
     * (sent as JSON with status 200, or 201 for POST), a Closure receiving
     * the Request, or a list of these, used in turn. Unkeyed values answer
     * any request, in order.
     *
     * @param  array<string|int, mixed>  $responses
     */
    public function respond(array $responses): self
    {
        foreach ($responses as $pattern => $response) {
            $this->transport->queue(is_int($pattern) ? '*' : $pattern, $response);
        }

        return $this;
    }

    /**
     * @return list<Request>
     */
    public function requests(): array
    {
        return $this->transport->requests();
    }

    /**
     * Assert a request matching the pattern was sent, optionally checking it.
     *
     * @param  (Closure(Request): bool)|null  $check
     */
    public function assertSent(string $pattern, ?Closure $check = null): void
    {
        $matching = $this->matching($pattern, $check);

        Assert::assertNotEmpty($matching, "Expected a request matching [{$pattern}]".($check instanceof Closure ? ' that passes the check' : '').'. Sent: '.$this->sentList());
    }

    /**
     * @param  (Closure(Request): bool)|null  $check
     */
    public function assertNotSent(string $pattern, ?Closure $check = null): void
    {
        Assert::assertEmpty($this->matching($pattern, $check), "Did not expect a request matching [{$pattern}]. Sent: ".$this->sentList());
    }

    public function assertSentCount(int $count, string $pattern = '*'): void
    {
        Assert::assertCount($count, $this->matching($pattern, null), "Expected {$count} request(s) matching [{$pattern}]. Sent: ".$this->sentList());
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->requests(), 'Expected no requests. Sent: '.$this->sentList());
    }

    /**
     * @param  (Closure(Request): bool)|null  $check
     * @return list<Request>
     */
    private function matching(string $pattern, ?Closure $check): array
    {
        return array_values(array_filter(
            $this->requests(),
            static fn (Request $request): bool => FakeTransport::matches($pattern, $request) && (! $check instanceof Closure || $check($request)),
        ));
    }

    private function sentList(): string
    {
        $sent = array_map(static fn (Request $request): string => $request->describe(), $this->requests());

        return $sent === [] ? 'nothing' : implode(', ', $sent);
    }
}
