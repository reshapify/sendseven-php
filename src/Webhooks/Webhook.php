<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks;

use JsonException;
use Psr\Http\Message\RequestInterface;
use Reshapify\SendSeven\Webhooks\Events\ChannelEvent;
use Reshapify\SendSeven\Webhooks\Events\ContactEvent;
use Reshapify\SendSeven\Webhooks\Events\ConversationEvent;
use Reshapify\SendSeven\Webhooks\Events\Event;
use Reshapify\SendSeven\Webhooks\Events\MessageReactionChanged;
use Reshapify\SendSeven\Webhooks\Events\MessageReceived;
use Reshapify\SendSeven\Webhooks\Events\MessageStatusUpdated;
use Reshapify\SendSeven\Webhooks\Events\UnknownEvent;
use SensitiveParameter;

/**
 * Receiving SendSeven webhooks, in three steps:
 *
 *     if (Webhook::isVerificationChallenge($body, $headers)) {
 *         return json(Webhook::challengeResponse($body));      // activates the endpoint
 *     }
 *
 *     $event = Webhook::constructEvent($body, $headers, $secret); // verifies, then parses
 *
 *     match (true) {
 *         $event instanceof MessageReceived => ...,
 *         $event instanceof MessageStatusUpdated => ...,
 *         default => null,
 *     };
 *
 * Always pass the raw body, exactly as received: re-encoding parsed JSON
 * changes the bytes and breaks the signature.
 */
final class Webhook
{
    /**
     * Whether this is the one-off challenge SendSeven sends when an endpoint
     * is created or its URL changes. It is unsigned; answer it with
     * challengeResponse() or the endpoint never activates.
     *
     * @param  array<string, string|list<string>>  $headers
     */
    public static function isVerificationChallenge(string $body, array $headers = []): bool
    {
        if ((Headers::normalize($headers)['x-sendseven-event'] ?? null) === 'verification') {
            return true;
        }

        $payload = self::decode($body);

        return ($payload['type'] ?? null) === 'sendseven_verification';
    }

    /**
     * The JSON object to reply to the challenge with (status 200).
     *
     * @return array{challenge: string}
     */
    public static function challengeResponse(string $body): array
    {
        $challenge = self::decode($body)['challenge'] ?? null;

        return ['challenge' => is_scalar($challenge) ? (string) $challenge : ''];
    }

    /**
     * Verify a delivery and parse it into a typed event.
     *
     * @param  array<string, string|list<string>>  $headers
     *
     * @throws InvalidSignature
     */
    public static function constructEvent(
        string $body,
        array $headers,
        #[SensitiveParameter] string $secret,
        int $toleranceSeconds = WebhookVerifier::DEFAULT_TOLERANCE_SECONDS,
        #[SensitiveParameter] ?string $authorization = null,
    ): Event {
        (new WebhookVerifier($secret, $toleranceSeconds, $authorization))->verify($body, $headers);

        return self::parse($body);
    }

    /**
     * The same, from a PSR-7 request.
     *
     * @throws InvalidSignature
     */
    public static function constructEventFromRequest(
        RequestInterface $request,
        #[SensitiveParameter] string $secret,
        int $toleranceSeconds = WebhookVerifier::DEFAULT_TOLERANCE_SECONDS,
        #[SensitiveParameter] ?string $authorization = null,
    ): Event {
        $headers = [];

        foreach (array_keys($request->getHeaders()) as $name) {
            $headers[(string) $name] = $request->getHeaderLine((string) $name);
        }

        return self::constructEvent((string) $request->getBody(), $headers, $secret, $toleranceSeconds, $authorization);
    }

    /**
     * Parse a delivery that has already been verified.
     *
     * @throws InvalidSignature when the body isn't a webhook event
     */
    public static function parse(string $body): Event
    {
        $payload = self::decode($body);
        $typeName = $payload['type'] ?? null;

        if (! is_string($typeName) || ! is_scalar($payload['id'] ?? null)) {
            throw InvalidSignature::malformedBody();
        }

        $type = EventType::tryFrom($typeName) ?? $typeName;

        return match ($type) {
            EventType::MessageReceived => MessageReceived::fromPayload($payload, $type),
            EventType::MessageSent, EventType::MessageDelivered, EventType::MessageRead, EventType::MessageFailed => MessageStatusUpdated::fromPayload($payload, $type),
            EventType::MessageReaction => MessageReactionChanged::fromPayload($payload, $type),
            EventType::ChannelCreated, EventType::ChannelUpdated, EventType::ChannelDeleted => ChannelEvent::fromPayload($payload, $type),
            EventType::ContactCreated, EventType::ContactUpdated, EventType::ContactDeleted => ContactEvent::fromPayload($payload, $type),
            EventType::ConversationCreated, EventType::ConversationClosed, EventType::ConversationAssigned,
            EventType::ConversationReopened, EventType::ConversationUpdated => ConversationEvent::fromPayload($payload, $type),
            default => UnknownEvent::fromPayload($payload, $type),
        };
    }

    /**
     * Headers that sign a body the way SendSeven does: for tests, fakes and
     * forwarding.
     *
     * @return array{X-SendSeven-Timestamp: string, X-SendSeven-Signature: string}
     */
    public static function sign(string $body, #[SensitiveParameter] string $secret, ?int $timestamp = null): array
    {
        $timestamp = (string) ($timestamp ?? time());

        return [
            'X-SendSeven-Timestamp' => $timestamp,
            'X-SendSeven-Signature' => WebhookVerifier::signature($secret, $timestamp, $body),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decode(string $body): array
    {
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }
}
