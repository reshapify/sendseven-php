<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

/**
 * The button or list row a customer tapped.
 */
final readonly class ButtonReply
{
    public function __construct(
        /** The id you gave the button or row when sending. */
        public string $id,
        public string $title,
        public bool $fromList = false,
    ) {}
}
