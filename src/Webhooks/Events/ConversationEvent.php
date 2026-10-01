<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Events;

use DateTimeImmutable;
use Reshapify\SendSeven\Support\Attributes;
use Reshapify\SendSeven\Webhooks\Data\Contact;
use Reshapify\SendSeven\Webhooks\Data\Conversation;
use Reshapify\SendSeven\Webhooks\EventType;

/**
 * A conversation was created, closed, assigned, reopened or updated.
 */
final readonly class ConversationEvent extends Event
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
            conversation: $attributes->nullableObject('conversation', Conversation::fromArray(...)),
            contact: $attributes->nullableObject('contact', Contact::fromArray(...)),
            raw: $payload,
        );
    }
}
