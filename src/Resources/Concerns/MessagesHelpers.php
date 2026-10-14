<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Resources\Concerns;

use Reshapify\SendSeven\Data\InteractiveMessage;
use Reshapify\SendSeven\Data\Message;
use Reshapify\SendSeven\Enums\InteractiveType;
use Reshapify\SendSeven\Enums\MessageStatus;
use Reshapify\SendSeven\Enums\MessageType;
use Reshapify\SendSeven\Exceptions\ApiException;
use Reshapify\SendSeven\Exceptions\MessageFailed;
use Reshapify\SendSeven\Exceptions\SendSevenException;

/**
 * @internal mixed into Messages
 */
trait MessagesHelpers
{
    /** WhatsApp's limits for interactive messages, in characters or items. */
    private const int INTERACTIVE_BODY_LIMIT = 1024;

    private const int FOOTER_LIMIT = 60;

    private const int MAX_BUTTONS = 3;

    private const int BUTTON_TITLE_LIMIT = 20;

    private const int MAX_LIST_ROWS = 10;

    private const int ROW_ID_LIMIT = 200;

    private const int ROW_TITLE_LIMIT = 24;

    private const int ROW_DESCRIPTION_LIMIT = 72;

    private const int SECTION_TITLE_LIMIT = 24;

    /**
     * Mark an inbound WhatsApp message as read (blue ticks), using the
     * platform's message ID: the externalId of a MessageReceived event.
     *
     * @throws ApiException
     */
    public function markWhatsAppMessageAsRead(string $channelId, string $externalMessageId): void
    {
        $this->proxy(
            channelId: $channelId,
            endpoint: 'messages',
            payload: ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => $externalMessageId],
        );
    }

    /**
     * Send a text message, to a contact or straight to an address (`to`),
     * and fail loudly when SendSeven answers with a failed message, which
     * send() returns like any other.
     *
     * @throws MessageFailed when SendSeven reports the message as failed
     * @throws SendSevenException
     */
    public function sendText(string $channelId, string $text, ?string $contactId = null, ?string $to = null, ?string $idempotencyKey = null): Message
    {
        $message = $this->send(to: $to, channelId: $channelId, contactId: $contactId, messageType: MessageType::Text, text: $text, idempotencyKey: $idempotencyKey);

        if ($message->status === MessageStatus::Failed || $message->status === 'failed') {
            throw MessageFailed::sending($message->id, $message->errorMessage ?? (is_scalar($message->meta['error'] ?? null) ? (string) $message->meta['error'] : null), is_scalar($message->meta['error_code'] ?? null) ? (string) $message->meta['error_code'] : null);
        }

        return $message;
    }

    /**
     * Send up to three reply buttons, trimmed to WhatsApp's limits. When
     * SendSeven refuses them (or the channel can't show them), the options
     * are sent as a numbered text message instead, unless $fallbackToText
     * is false; the customer replies with the number.
     *
     * @param  array<array-key, string>  $buttons  The id you'll get back => the title shown
     *
     * @throws MessageFailed when the message (or its fallback) failed
     * @throws SendSevenException
     */
    public function sendButtons(string $channelId, string $contactId, string $body, array $buttons, ?string $footer = null, bool $fallbackToText = true): InteractiveMessage|Message
    {
        $buttons = array_slice($buttons, 0, self::MAX_BUTTONS, true);
        $payload = [];

        foreach ($buttons as $id => $title) {
            $payload[] = ['id' => (string) $id, 'title' => mb_substr($title, 0, self::BUTTON_TITLE_LIMIT)];
        }

        return $this->interactiveOrText(
            fn (): InteractiveMessage => $this->sendInteractive(
                channelId: $channelId,
                contactId: $contactId,
                type: InteractiveType::Buttons,
                body: mb_substr($body, 0, self::INTERACTIVE_BODY_LIMIT),
                footer: $footer === null ? null : mb_substr($footer, 0, self::FOOTER_LIMIT),
                buttons: $payload,
            ),
            $fallbackToText ? fn (): Message => $this->sendNumbered($channelId, $contactId, $body, array_values($buttons)) : null,
        );
    }

    /**
     * Send a list menu of up to ten rows in one section, trimmed to
     * WhatsApp's limits, with the same numbered-text fallback as
     * sendButtons().
     *
     * @param  list<array{id: string, title: string, description?: string|null}>  $rows
     *
     * @throws MessageFailed when the message (or its fallback) failed
     * @throws SendSevenException
     */
    public function sendList(string $channelId, string $contactId, string $body, string $buttonText, array $rows, string $sectionTitle = 'Options', ?string $footer = null, bool $fallbackToText = true): InteractiveMessage|Message
    {
        $rows = array_slice($rows, 0, self::MAX_LIST_ROWS);
        $payload = [];

        foreach ($rows as $row) {
            $description = trim($row['description'] ?? '');

            $payload[] = [
                'id' => mb_substr($row['id'], 0, self::ROW_ID_LIMIT),
                'title' => mb_substr($row['title'], 0, self::ROW_TITLE_LIMIT),
                ...($description === '' ? [] : ['description' => mb_substr($description, 0, self::ROW_DESCRIPTION_LIMIT)]),
            ];
        }

        return $this->interactiveOrText(
            fn (): InteractiveMessage => $this->sendInteractive(
                channelId: $channelId,
                contactId: $contactId,
                type: InteractiveType::List,
                body: mb_substr($body, 0, self::INTERACTIVE_BODY_LIMIT),
                footer: $footer === null ? null : mb_substr($footer, 0, self::FOOTER_LIMIT),
                sections: [['title' => mb_substr($sectionTitle, 0, self::SECTION_TITLE_LIMIT), 'rows' => $payload]],
                buttonText: mb_substr($buttonText, 0, self::BUTTON_TITLE_LIMIT),
            ),
            $fallbackToText ? fn (): Message => $this->sendNumbered($channelId, $contactId, $body, array_column($rows, 'title')) : null,
        );
    }

    /**
     * @param  callable(): InteractiveMessage  $interactive
     * @param  (callable(): Message)|null  $fallback
     */
    private function interactiveOrText(callable $interactive, ?callable $fallback): InteractiveMessage|Message
    {
        try {
            $message = $interactive();

            if ($message->status === 'failed') {
                throw MessageFailed::sending($message->id, $message->error, $message->errorCode);
            }

            return $message;
        } catch (ApiException|MessageFailed $exception) {
            if ($fallback === null) {
                throw $exception;
            }

            return $fallback();
        }
    }

    /**
     * @param  list<string>  $options
     */
    private function sendNumbered(string $channelId, string $contactId, string $body, array $options): Message
    {
        $lines = [];

        foreach ($options as $index => $option) {
            $lines[] = ($index + 1).'. '.$option;
        }

        return $this->sendText($channelId, $body."\n\n".implode("\n", $lines)."\n\n_Reply with a number._", contactId: $contactId);
    }
}
