<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Onboarding;

/**
 * How a WhatsApp number is connected through a connect link.
 */
enum WhatsAppMode: string
{
    /** The standard Cloud API onboarding (Meta's Embedded Signup): the number is used only through the API. */
    case Classic = 'classic';

    /** The number keeps working in the WhatsApp Business app on the phone as well as through the API. */
    case Coexistence = 'coexistence';
}
