<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Onboarding;

/**
 * How a connect link sends the customer back to your redirect URL.
 */
enum RedirectMode: string
{
    /** After each connection a "Continue to {partner}" button; the customer can connect another channel first. SendSeven's default. */
    case Button = 'button';

    /** Straight back to you after the first successful connection, following a short confirmation. */
    case AutoAfterFirst = 'auto_after_first';
}
