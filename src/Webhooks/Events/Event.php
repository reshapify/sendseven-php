<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Events;

use DateTimeImmutable;
use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Webhooks\EventType;

/**
 * A webhook delivery. Match on the subclass (MessageReceived,
 * MessageStatusUpdated, ChannelCreated…) or on $type.
 */
abstract readonly class Event extends Data
{
    /**
     * @param  array<array-key, mixed>  $data  the event's "data" object
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        /** Unique per event: use it to ignore redeliveries. */
        public string $id,
        public EventType|string $type,
        /** The underlying occurrence; the same on every retry (often the message ID). */
        public ?string $eventId,
        public ?DateTimeImmutable $createdAt,
        public ?string $tenantId,
        public array $data,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * The type as a string, e.g. "message.received", whether or not the SDK
     * knows it.
     */
    public function typeName(): string
    {
        return $this->type instanceof EventType ? $this->type->value : $this->type;
    }
}
