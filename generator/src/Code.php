<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Small helpers for writing PHP source.
 */
final class Code
{
    /**
     * A PHPDoc block from lines, wrapped at 100 columns.
     *
     * @param  list<string>  $lines  "" for a blank line
     */
    public static function docblock(array $lines, string $indent = ''): string
    {
        $lines = array_values(array_filter($lines, static fn (?string $line): bool => $line !== null));

        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        if ($lines === []) {
            return '';
        }

        $out = [$indent.'/**'];

        foreach ($lines as $line) {
            if ($line === '') {
                $out[] = $indent.' *';

                continue;
            }

            $wrapped = str_starts_with($line, '@') ? [$line] : explode("\n", wordwrap($line, 96, "\n", false));

            foreach ($wrapped as $piece) {
                $out[] = $indent.' * '.self::escapeComment($piece);
            }
        }

        $out[] = $indent.' */';

        return implode("\n", $out)."\n";
    }

    /**
     * Prose from the spec, cleaned for a docblock: markdown emphasis and
     * code fences removed, whitespace collapsed, one paragraph per line.
     *
     * @return list<string>
     */
    public static function prose(string $text, int $maxParagraphs = 3): array
    {
        $text = (string) preg_replace('/```.*?```/s', '', $text);
        $text = str_replace(['**', '`'], '', $text);
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $lines = [];

        foreach (array_slice($paragraphs, 0, $maxParagraphs) as $paragraph) {
            $paragraph = trim((string) preg_replace('/\s+/', ' ', $paragraph));

            if ($paragraph !== '') {
                $lines[] = $paragraph;
                $lines[] = '';
            }
        }

        return $lines;
    }

    public static function string(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    /**
     * @param  list<string>  $imports  FQCNs
     */
    public static function uses(array $imports, string $ownNamespace): string
    {
        $imports = array_values(array_unique(array_filter(
            $imports,
            static fn (string $class): bool => str_contains($class, '\\') ? substr($class, 0, (int) strrpos($class, '\\')) !== $ownNamespace : $class !== '',
        )));
        sort($imports);

        return $imports === [] ? '' : implode("\n", array_map(static fn (string $class): string => "use {$class};", $imports))."\n\n";
    }

    private static function escapeComment(string $line): string
    {
        return str_replace('*/', '*\\/', $line);
    }
}
