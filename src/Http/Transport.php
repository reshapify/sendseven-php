<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

use Reshapify\SendSeven\Exceptions\TransportFailed;

/**
 * Puts a request on the wire. Implementations do no retrying or error
 * mapping: they return whatever status SendSeven answered with.
 */
interface Transport
{
    /**
     * @throws TransportFailed when no response arrived (DNS, TLS, timeout…)
     */
    public function send(Request $request): Response;
}
