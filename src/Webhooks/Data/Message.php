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
     * The button or list row the customer tapped. SendSeven reports it in
     * meta.button, a button_reply attachment, or an interactive list_reply.
     */
    public function buttonReply(): ?ButtonReply
    {
        $button = $this->meta['button'] ?? null;

        if (is_array($button) && is_scalar($button['id'] ?? null)) {
            return new ButtonReply((string) $button['id'], is_scalar($button['text'] ?? null) ? (string) $button['text'] : (string) $this->text);
        }

        foreach ($this->attachments as $attachment) {
            $reply = $attachment->raw();

            if ($attachment->type === 'button_reply' && is_scalar($reply['button_id'] ?? null)) {
                return new ButtonReply((string) $reply['button_id'], is_scalar($reply['button_title'] ?? null) ? (string) $reply['button_title'] : (string) $this->text);
            }
        }

        $interactive = $this->raw()['interactive'] ?? null;
        $row = is_array($interactive) ? ($interactive['list_reply'] ?? $interactive['button_reply'] ?? null) : null;

        if (is_array($row) && is_scalar($row['id'] ?? null)) {
            return new ButtonReply((string) $row['id'], is_scalar($row['title'] ?? null) ? (string) $row['title'] : (string) $this->text, isset($interactive['list_reply']));
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
}
