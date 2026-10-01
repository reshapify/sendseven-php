<?php

declare(strict_types=1);

namespace Reshapify\SendSeven;

use Reshapify\SendSeven\Testing\Fake;
use SensitiveParameter;

/**
 * Where everything starts.
 *
 *     $sendseven = SendSeven::client(getenv('SENDSEVEN_API_TOKEN'));
 *     $sendseven->messages()->send(...);
 */
final class SendSeven
{
    public const string VERSION = '0.1.1';

    public const string BASE_URI = 'https://api.sendseven.com/api/v1';

    /**
     * A client for one tenant's API token, with sensible defaults: the HTTP
     * client already installed, three attempts for safe retries, and an
     * idempotency key on every write.
     */
    public static function client(#[SensitiveParameter] string $token): Client
    {
        return self::factory()->withToken($token)->make();
    }

    /**
     * Configure a client: base URI, HTTP client, retries, rate limiting, tenant.
     */
    public static function factory(): Factory
    {
        return new Factory;
    }

    /**
     * A client that sends nothing: it answers with the responses you give it
     * and records every request for assertions.
     *
     *     $fake = SendSeven::fake(['POST /messages' => ['id' => 'msg_1', ...]]);
     *     $fake->client->messages()->send(...);
     *     $fake->assertSent('POST /messages');
     *
     * @param  array<string|int, mixed>  $responses  see Fake::respond()
     */
    public static function fake(array $responses = []): Fake
    {
        return new Fake($responses);
    }
}
