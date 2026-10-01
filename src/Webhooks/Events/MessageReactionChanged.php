<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Events;

use DateTimeImmutable;
use Reshapify\SendSeven\Support\Attributes;
use Reshapify\SendSeven\Webhooks\Data\Contact;
use Reshapify\SendSeven\Webhooks\Data\Conversation;
use Reshapify\SendSeven\Webhooks\Data\Message;
use Reshapify\SendSeven\Webhooks\Data\Reaction;
use Reshapify\SendSeven\Webhooks\EventType;

/**
 * An emoji reaction was added to or removed from a message (free, never billed).
 */
final readonly class MessageReactionChanged extends Event
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
        public Reaction $reaction,
        public Message $message,
        public ?Conversation $conversation,
        public ?Contact $contact,
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
            reaction: $attributes->object('reaction', Reaction::fromArray(...)),
            message: $attributes->object('message', Message::fromArray(...)),
            conversation: $attributes->nullableObject('conversation', Conversation::fromArray(...)),
            contact: $attributes->nullableObject('contact', Contact::fromArray(...)),
            raw: $payload,
        );
    }
}
