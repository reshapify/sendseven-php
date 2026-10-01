<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Enums\ContactMethodType;
use Reshapify\SendSeven\Support\Attributes;

/**
 * One way to reach a contact: a phone number, an email address, or a
 * platform ID such as a Telegram chat ID.
 */
final readonly class ContactMethod extends Data
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ContactMethodType|string $type,
        public string $value,
        /** Set for channel-scoped methods (whatsapp_bsuid, messenger_id, instagram_id). */
        public ?string $channelId,
        public ?string $displayName,
        public bool $isPrimary,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'contact_method'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->string('id'),
            type: $attributes->enum('method_type', ContactMethodType::class),
            value: $attributes->string('value'),
            channelId: $attributes->nullableString('channel_id'),
            displayName: $attributes->nullableString('display_name'),
            isPrimary: $attributes->bool('is_primary', default: false),
            raw: $data,
        );
    }
}
