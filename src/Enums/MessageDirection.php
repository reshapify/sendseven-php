<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Enums;

enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
