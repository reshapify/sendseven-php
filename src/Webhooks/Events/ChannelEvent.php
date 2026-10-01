<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Events;

use DateTimeImmutable;
use Reshapify\SendSeven\Support\Attributes;
use Reshapify\SendSeven\Webhooks\Data\Channel;
use Reshapify\SendSeven\Webhooks\EventType;

/**
 * A channel was created, updated (connected, disconnected, edited, restored) or deleted.
 */
final readonly class ChannelEvent extends Event
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        string $id,
        EventType|string $type,
        ?string $eventId,
        ?DateTimeImmutable $createdAt,
        ?string $tenantId,
        array $data,
        public Channel $channel,
        array $raw = [],
    ) {
        parent::__construct($id, $type, $eventId, $createdAt, $tenantId, $data, $raw);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(array $payload, EventType|string $type): self
    {
        $envelope = new Attributes($payload, 'event');
        $attributes = new Attributes($envelope->array('data'), 'event.data');

        return new self(
            id: $envelope->string('id'),
            type: $type,
            eventId: $envelope->nullableString('event_id'),
            createdAt: $envelope->nullableDateTime('created_at'),
            tenantId: $envelope->nullableString('tenant_id'),
            data: $envelope->array('data'),
            channel: $attributes->object('channel', Channel::fromArray(...)),
            raw: $payload,
        );
    }

    /**
     * For channel.updated: status_changed, settings_updated or restored.
     */
    public function change(): ?string
    {
        $change = $this->data['change'] ?? null;

        return is_string($change) ? $change : null;
    }

    /**
     * Whether this channel was created through the given connect link, so a
     * new channel can be tied back to the customer who connected it.
     */
    public function wasConnectedVia(string $connectLinkId): bool
    {
        return $this->type === EventType::ChannelCreated && $this->channel->createdViaConnectTokenId === $connectLinkId;
    }
}
