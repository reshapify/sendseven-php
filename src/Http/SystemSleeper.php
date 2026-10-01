<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

final readonly class SystemSleeper implements Sleeper
{
    public function sleep(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
