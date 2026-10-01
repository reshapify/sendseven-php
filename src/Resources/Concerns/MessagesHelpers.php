<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Resources\Concerns;

use Reshapify\SendSeven\Exceptions\ApiException;

/**
 * @internal mixed into Messages
 */
trait MessagesHelpers
{
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
}
