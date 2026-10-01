<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use DateTimeImmutable;
use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Support\Attributes;

final readonly class Conversation extends Data
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $channelId,
        public ?string $contactId,
        public ?string $status,
        public ?string $subject,
        public ?string $assignedUserId,
        public ?DateTimeImmutable $lastCustomerMessageAt,
        public ?DateTimeImmutable $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'conversation'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->string('id'),
            channelId: $attributes->nullableString('channel_id'),
            contactId: $attributes->nullableString('contact_id'),
            status: $attributes->nullableString('status'),
            subject: $attributes->nullableString('subject'),
            assignedUserId: $attributes->nullableString('assigned_user_id'),
            lastCustomerMessageAt: $attributes->nullableDateTime('last_customer_message_at'),
            createdAt: $attributes->nullableDateTime('created_at'),
            raw: $data,
        );
    }
}
