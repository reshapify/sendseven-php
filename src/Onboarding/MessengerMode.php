<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Onboarding;

enum MessengerMode: string
{
    /** Messages to a Facebook Page. */
    case BusinessMessaging = 'business_messaging';

    /** Comments on a Page's posts. */
    case BusinessSocial = 'business_social';

    /** A personal profile's posts. */
    case PersonalSocial = 'personal_social';
}
