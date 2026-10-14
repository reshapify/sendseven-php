<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use DateTimeImmutable;
use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Enums\MessageDirection;
use Reshapify\SendSeven\Support\Attributes;

/**
 * A message as it appears in message.* webhook events.
 *
 * The sender is fromId (a phone number, or the channel's scoped ID on
 * Telegram, Messenger and Instagram); files are in attachments.
 */
final readonly class Message extends Data
{
    /**
     * @param  list<Attachment>  $attachments
     * @param  array<array-key, mixed>  $meta
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $conversationId,
        public ChannelType|string|null $platform,
        public ?string $channelId,
        public ?string $contactId,
        public ?string $contactMethodId,
        public MessageDirection|string|null $direction,
        /** text, image, video, audio, document, sticker, location, contact, interactive, button, reaction… */
        public string $type,
        /** The text, or a media caption ("" when there is none). */
        public ?string $text,
        public array $attachments,
        public ?string $status,
        public ?string $fromId,
        public ?string $toId,
        /** The platform's own message ID, e.g. a WhatsApp wamid. */
        public ?string $externalId,
        public ?string $replyToExternalId,
        public array $meta,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $deliveredAt,
        public ?DateTimeImmutable $readAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'message'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->string('id'),
            conversationId: $attributes->nullableString('conversation_id'),
            platform: $attributes->nullableEnum('platform', ChannelType::class),
            channelId: $attributes->nullableString('channel_id'),
            contactId: $attributes->nullableString('contact_id'),
            contactMethodId: $attributes->nullableString('contact_method_id'),
            direction: $attributes->nullableEnum('direction', MessageDirection::class),
            type: $attributes->nullableString('message_type') ?? 'text',
            text: $attributes->nullableString('text'),
            attachments: $attributes->list('attachments', Attachment::fromArray(...)),
            status: $attributes->nullableString('status'),
            fromId: $attributes->nullableString('from_id'),
            toId: $attributes->nullableString('to_id'),
            externalId: $attributes->nullableString('external_id'),
            replyToExternalId: $attributes->nullableString('reply_to_external_id'),
            meta: $attributes->array('meta'),
            createdAt: $attributes->nullableDateTime('created_at'),
            sentAt: $attributes->nullableDateTime('sent_at'),
            deliveredAt: $attributes->nullableDateTime('delivered_at'),
            readAt: $attributes->nullableDateTime('read_at'),
            raw: $data,
        );
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    /**
     * The first file on the message, if any.
     */
    public function attachment(): ?Attachment
    {
        foreach ($this->attachments as $attachment) {
            if (in_array($attachment->type, ['image', 'video', 'audio', 'document', 'sticker', 'file'], true)) {
                return $attachment;
            }
        }

        return null;
    }

    /**
     * Every image on the message. Some deliveries type an image as "photo",
     * or leave an image message's attachment untyped.
     *
     * @return list<Attachment>
     */
    public function images(): array
    {
        return array_values(array_filter(
            $this->attachments,
            fn (Attachment $attachment): bool => in_array($attachment->type, ['image', 'photo'], true)
                || ($this->type === 'image' && ! isset($attachment->raw()['type'])),
        ));
    }

    /**
     * The location the customer shared, from a location attachment.
     */
    public function location(): ?Location
    {
        foreach ($this->attachments as $attachment) {
            $data = $attachment->raw();

            if ($attachment->type === 'location' && is_numeric($data['latitude'] ?? null) && is_numeric($data['longitude'] ?? null)) {
                return new Location(
                    (float) $data['latitude'],
                    (float) $data['longitude'],
                    $this->filled($data['name'] ?? null),
                    $this->filled($data['address'] ?? null),
                );
            }
        }

        return null;
    }

    /**
     * What the customer typed, or the caption on their media. Null when
     * SendSeven wrote the text itself: it summarises reactions, stickers,
     * locations and shared contacts into text, which isn't customer input.
     */
    public function typedText(): ?string
    {
        if (in_array($this->type, ['reaction', 'sticker', 'location', 'contact', 'contacts'], true)) {
            return null;
        }

        return $this->filled($this->text);
    }

    /**
     * The button or list row the customer tapped. SendSeven reports it in
     * one of several places: an interactive reply (top level or under meta),
     * meta.button (a template quick reply's payload, or an id), or a
     * button_reply or list_reply attachment.
     */
    public function buttonReply(): ?ButtonReply
    {
        $data = $this->raw();

        foreach ([$data['interactive'] ?? null, $this->meta['interactive'] ?? null] as $interactive) {
            if (! is_array($interactive)) {
                continue;
            }

            foreach (['button_reply' => false, 'list_reply' => true] as $key => $fromList) {
                $row = $interactive[$key] ?? null;

                if (is_array($row) && ($id = $this->filled($row['id'] ?? null)) !== null) {
                    return new ButtonReply($id, $this->filled($row['title'] ?? null) ?? (string) $this->text, $fromList);
                }
            }
        }

        $button = $this->meta['button'] ?? null;

        if (is_array($button) && ($id = $this->filled($button['payload'] ?? null) ?? $this->filled($button['id'] ?? null)) !== null) {
            return new ButtonReply($id, $this->filled($button['text'] ?? null) ?? (string) $this->text);
        }

        foreach ($this->attachments as $attachment) {
            if (! in_array($attachment->type, ['button_reply', 'list_reply'], true)) {
                continue;
            }

            $reply = $attachment->raw();
            $id = $this->filled($reply['button_id'] ?? null)
                ?? $this->filled($reply['row_id'] ?? null)
                ?? $this->filled($reply['payload'] ?? null)
                ?? $this->filled($reply['id'] ?? null);

            if ($id !== null) {
                $title = $this->filled($reply['button_title'] ?? null) ?? $this->filled($reply['title'] ?? null) ?? (string) $this->text;

                return new ButtonReply($id, $title, $attachment->type === 'list_reply');
            }
        }

        return null;
    }

    /**
     * Why a failed message failed: the provider's code, e.g. "131047"
     * (outside WhatsApp's 24-hour window) or "rcs_not_reachable".
     */
    public function errorCode(): ?string
    {
        $code = $this->meta['error_code'] ?? null;

        return is_scalar($code) ? (string) $code : null;
    }

    public function errorMessage(): ?string
    {
        $message = $this->meta['error'] ?? null;

        return is_scalar($message) ? (string) $message : null;
    }

    /**
     * Sender details SendSeven sometimes adds to inbound messages (name,
     * phone, WhatsApp username and business-scoped ID).
     *
     * @return array<array-key, mixed>
     */
    public function contactInfo(): array
    {
        $info = $this->raw()['contact_info'] ?? null;

        return is_array($info) ? $info : [];
    }

    private function filled(mixed $value): ?string
    {
        if (! is_scalar($value) || is_bool($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
