<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Support\Attributes;

final readonly class Reaction extends Data
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        /** May be "" when a reaction is removed. */
        public string $emoji,
        public bool $added,
        public ?string $fromId,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'reaction'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            emoji: $attributes->nullableString('emoji') ?? '',
            added: $attributes->nullableString('action') !== 'removed',
            fromId: $attributes->nullableString('from_id'),
            raw: $data,
        );
    }
}
