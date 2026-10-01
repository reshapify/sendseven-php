<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Exceptions;

use Stringable;

/**
 * One invalid field: where it is ("body.text"), what's wrong and the
 * validator's error type ("missing").
 */
final readonly class FieldError implements Stringable
{
    public function __construct(
        public string $field,
        public string $message,
        public string $type,
    ) {}

    public function __toString(): string
    {
        return $this->field === '' ? $this->message : "{$this->field}: {$this->message}";
    }

    public static function fromArray(mixed $error): ?self
    {
        if (! is_array($error)) {
            return null;
        }

        $location = is_array($error['loc'] ?? null) ? $error['loc'] : [];
        $field = implode('.', array_map(static fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $location));

        return new self(
            $field,
            is_string($error['msg'] ?? null) ? $error['msg'] : 'is invalid',
            is_string($error['type'] ?? null) ? $error['type'] : 'invalid',
        );
    }
}
