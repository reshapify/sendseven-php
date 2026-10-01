<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

/**
 * Waits between retries. Swapped out in tests so they never actually sleep.
 */
interface Sleeper
{
    public function sleep(int $milliseconds): void;
}
