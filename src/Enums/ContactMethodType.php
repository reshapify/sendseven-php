<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Enums;

/**
 * How a contact can be reached. SendSeven adds types as platforms change
 * (whatsapp_bsuid is the most recent), so unknown values arrive as strings.
 */
enum ContactMethodType: string
{
    case Phone = 'phone';
    case Email = 'email';
    case WhatsAppId = 'whatsapp_id';
    /** A WhatsApp business-scoped user ID; scoped to one channel, and a contact may have it without a phone number. */
    case WhatsAppBsuid = 'whatsapp_bsuid';
    case TelegramId = 'telegram_id';
    case MessengerId = 'messenger_id';
    case InstagramId = 'instagram_id';
    case LinkedIn = 'linkedin';
    case Homepage = 'homepage';
    case Facebook = 'facebook';
    case InstagramHandle = 'instagram_handle';
    case SocialOther = 'social_other';
}
