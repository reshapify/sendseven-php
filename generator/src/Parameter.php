<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

final readonly class Parameter
{
    public function __construct(
        public string $name,
        /** the name on the wire */
        public string $wire,
        /** path, query, body, multipart, raw-body or idempotency */
        public string $in,
        public PhpType $type,
        public bool $required,
        public string $description = '',
    ) {}

    public function signature(): string
    {
        return $this->type->native.' $'.$this->name.($this->required ? '' : ' = null');
    }
}
