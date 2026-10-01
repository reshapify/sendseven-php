<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Data;

use JsonSerializable;

/**
 * A typed object built from SendSeven's JSON. raw() returns the original,
 * including any fields the SDK doesn't model yet.
 */
abstract readonly class Data implements JsonSerializable
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(private array $raw = []) {}

    /**
     * @return array<array-key, mixed>
     */
    final public function raw(): array
    {
        return $this->raw;
    }

    /**
     * @return array<array-key, mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
