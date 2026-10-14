<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

/**
 * A location the customer shared.
 */
final readonly class Location
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $name = null,
        public ?string $address = null,
    ) {}
}
