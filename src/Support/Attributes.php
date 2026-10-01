<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Support;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use Exception;
use Reshapify\SendSeven\Exceptions\UnexpectedResponse;

/**
 * Reads typed values out of a decoded JSON object, naming the exact field
 * when one is missing or has the wrong type.
 *
 * @internal used by the generated and curated response objects
 */
final readonly class Attributes
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  string  $path  where this object sits in the response, for error messages
     */
    public function __construct(private array $data, private string $path = 'response') {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function string(string $key): string
    {
        return $this->nullableString($key) ?? throw $this->missing($key);
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            default => throw $this->wrongType($key, 'a string', $value),
        };
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key) ?? throw $this->missing($key);
    }

    public function nullableInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_int($value) => $value,
            is_float($value) && floor($value) === $value => (int) $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw $this->wrongType($key, 'an integer', $value),
        };
    }

    public function float(string $key): float
    {
        return $this->nullableFloat($key) ?? throw $this->missing($key);
    }

    public function nullableFloat(string $key): ?float
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_int($value), is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => throw $this->wrongType($key, 'a number', $value),
        };
    }

    public function bool(string $key, ?bool $default = null): bool
    {
        return $this->nullableBool($key) ?? $default ?? throw $this->missing($key);
    }

    public function nullableBool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_bool($value) => $value,
            default => throw $this->wrongType($key, 'a boolean', $value),
        };
    }

    public function dateTime(string $key): DateTimeImmutable
    {
        return $this->nullableDateTime($key) ?? throw $this->missing($key);
    }

    public function nullableDateTime(string $key): ?DateTimeImmutable
    {
        $value = $this->nullableString($key);

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw $this->wrongType($key, 'a date and time', $value);
        }
    }

    /**
     * A JSON object, as an array.
     *
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        return $this->nullableArray($key) ?? [];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function nullableArray(string $key): ?array
    {
        $value = $this->data[$key] ?? null;

        return match (true) {
            $value === null => null,
            is_array($value) => $value,
            default => throw $this->wrongType($key, 'an object or list', $value),
        };
    }

    /**
     * @return list<string>
     */
    public function strings(string $key): array
    {
        return array_map(
            fn (mixed $item): string => is_scalar($item) ? (string) $item : throw $this->wrongType($key, 'a list of strings', $item),
            array_values($this->array($key)),
        );
    }

    /**
     * A nested object, built by $make.
     *
     * @template T
     *
     * @param  Closure(array<array-key, mixed>, string): T  $make  receives the object and its path
     * @return T
     */
    public function object(string $key, Closure $make): mixed
    {
        return $this->nullableObject($key, $make) ?? throw $this->missing($key);
    }

    /**
     * @template T
     *
     * @param  Closure(array<array-key, mixed>, string): T  $make
     * @return T|null
     */
    public function nullableObject(string $key, Closure $make): mixed
    {
        $value = $this->nullableArray($key);

        return $value === null ? null : $make($value, "{$this->path}.{$key}");
    }

    /**
     * A list of nested objects, each built by $make.
     *
     * @template T
     *
     * @param  Closure(array<array-key, mixed>, string): T  $make
     * @return list<T>
     */
    public function list(string $key, Closure $make): array
    {
        $items = [];

        foreach (array_values($this->array($key)) as $index => $item) {
            $items[] = is_array($item) ? $make($item, "{$this->path}.{$key}[{$index}]") : throw $this->wrongType("{$key}[{$index}]", 'an object', $item);
        }

        return $items;
    }

    /**
     * An enum value. SendSeven adds values over time, so an unknown one is
     * returned as the plain string instead of failing.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|string
     */
    public function enum(string $key, string $enum): BackedEnum|string
    {
        return $this->nullableEnum($key, $enum) ?? throw $this->missing($key);
    }

    /**
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|string|null
     */
    public function nullableEnum(string $key, string $enum): BackedEnum|string|null
    {
        $value = $this->nullableString($key);

        return $value === null ? null : ($enum::tryFrom($value) ?? $value);
    }

    private function missing(string $key): UnexpectedResponse
    {
        return UnexpectedResponse::because("{$this->path}.{$key} is missing");
    }

    private function wrongType(string $key, string $expected, mixed $value): UnexpectedResponse
    {
        return UnexpectedResponse::because("{$this->path}.{$key} should be {$expected}, got ".get_debug_type($value));
    }
}
