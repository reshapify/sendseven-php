<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks;

/**
 * @internal
 */
final class Headers
{
    /**
     * Lower-cased names with a single string value each, whatever shape the
     * framework handed over (PSR-7 lists, $_SERVER-style HTTP_ keys…).
     *
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>
     */
    public static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $name = strtolower(str_replace('_', '-', (string) preg_replace('/^HTTP_/i', '', $name)));
            $normalized[$name] = is_array($value) ? ($value[0] ?? '') : $value;
        }

        return $normalized;
    }
}
