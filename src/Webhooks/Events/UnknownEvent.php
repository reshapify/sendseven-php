<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Events;

use Reshapify\SendSeven\Support\Attributes;
use Reshapify\SendSeven\Webhooks\EventType;

/**
 * An event without a dedicated class (campaign, comment, post, email…
 * events, or one SendSeven added recently). Its data is in $data.
 */
final readonly class UnknownEvent extends Event
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromPayload(array $payload, EventType|string $type): self
    {
        $envelope = new Attributes($payload, 'event');

        return new self(
            id: $envelope->string('id'),
            type: $type,
            eventId: $envelope->nullableString('event_id'),
            createdAt: $envelope->nullableDateTime('created_at'),
            tenantId: $envelope->nullableString('tenant_id'),
            data: $envelope->array('data'),
            raw: $payload,
        );
    }
}
