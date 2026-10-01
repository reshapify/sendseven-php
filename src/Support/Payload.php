<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Support;

use BackedEnum;
use DateTimeInterface;
use Reshapify\SendSeven\Http\FilePart;

/**
 * Turns method arguments into what SendSeven expects on the wire: optional
 * arguments left as null are omitted, enums become their values and dates
 * become ISO 8601 strings.
 *
 * @internal used by the generated resources
 */
final class Payload
{
    /**
     * A JSON body.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function body(array $fields): array
    {
        $body = [];

        foreach ($fields as $name => $value) {
            if ($value !== null) {
                $body[$name] = self::value($value);
            }
        }

        return $body;
    }

    /**
     * Query parameters.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, scalar|list<scalar>|null>
     */
    public static function query(array $fields): array
    {
        $query = [];

        foreach ($fields as $name => $value) {
            $value = self::value($value);

            if (is_scalar($value) || $value === null) {
                $query[$name] = $value;
            } elseif (is_array($value)) {
                $query[$name] = array_values(array_filter($value, is_scalar(...)));
            }
        }

        return $query;
    }

    /**
     * multipart/form-data fields.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, scalar|FilePart|list<scalar|FilePart>|null>
     */
    public static function multipart(array $fields): array
    {
        $parts = [];

        foreach ($fields as $name => $value) {
            $value = $value instanceof FilePart ? $value : self::value($value);

            if (is_scalar($value) || $value instanceof FilePart || $value === null) {
                $parts[$name] = $value;
            } elseif (is_array($value)) {
                $parts[$name] = array_values(array_filter($value, static fn (mixed $item): bool => is_scalar($item) || $item instanceof FilePart));
            }
        }

        return $parts;
    }

    /**
     * A URL path segment.
     */
    public static function segment(BackedEnum|string|int $value): string
    {
        return rawurlencode((string) ($value instanceof BackedEnum ? $value->value : $value));
    }

    private static function value(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            is_array($value) => array_map(self::value(...), $value),
            default => $value,
        };
    }
}
