<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Onboarding;

enum InstagramMode: string
{
    /** Direct messages. */
    case Messaging = 'messaging';

    /** Comments on posts. */
    case Social = 'social';
}
