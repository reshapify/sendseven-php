<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Enums;

/**
 * A messaging platform SendSeven connects to. Also called "platform" in
 * webhook payloads.
 */
enum ChannelType: string
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
    case Telegram = 'telegram';
    case Messenger = 'messenger';
    case Instagram = 'instagram';
    case Rcs = 'rcs';
    case Viber = 'viber';
    case BrowserPush = 'browser_push';
    case LiveChat = 'live_chat';

    /**
     * Whether people on this channel are addressed by an ID the channel
     * scopes to your bot, page or account (and only learned once they message
     * you), rather than by phone number or email.
     */
    public function usesScopedIds(): bool
    {
        return match ($this) {
            self::Telegram, self::Messenger, self::Instagram => true,
            default => false,
        };
    }

    /**
     * Hours after the customer's last message during which free-form messages
     * may be sent, for channels that enforce a window.
     */
    public function replyWindowHours(): ?int
    {
        return match ($this) {
            self::WhatsApp, self::Messenger, self::Instagram => 24,
            default => null,
        };
    }
}
