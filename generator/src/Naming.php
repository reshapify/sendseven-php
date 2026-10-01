<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * How spec names become PHP names.
 */
final class Naming
{
    /** Words written with one capital, whatever the spec's capitalisation. */
    private const array WORDS = [
        'whatsapp' => 'WhatsApp', 'sms' => 'Sms', 'kb' => 'Kb', 'faq' => 'Faq', 'ai' => 'Ai', 'api' => 'Api',
        'oauth2' => 'OAuth2', 'oauth' => 'OAuth', 'id' => 'Id', 'url' => 'Url', 'rcs' => 'Rcs', 'hubspot' => 'HubSpot',
    ];

    private const array RESERVED = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue',
        'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
        'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
        'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'interface', 'isset',
        'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield', 'mixed',
        'object', 'string', 'int', 'float', 'bool', 'null', 'true', 'false', 'void', 'never', 'iterable', 'self', 'parent',
    ];

    /**
     * "WhatsApp Templates" → "WhatsAppTemplates"; "MessageStatus-Output" → "MessageStatus".
     */
    public static function studly(string $value): string
    {
        $value = (string) preg_replace('/-(Input|Output)$/', '', $value);
        $value = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value);
        $value = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', $value);
        $words = preg_split('/[^A-Za-z0-9]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $studly = implode('', array_map(static fn (string $word): string => self::WORDS[strtolower($word)] ?? ucfirst(strtolower($word)), $words));

        return preg_match('/^\d/', $studly) === 1 ? 'N'.$studly : $studly;
    }

    public static function camel(string $value): string
    {
        $studly = self::studly($value);

        return lcfirst($studly);
    }

    /**
     * A variable or property name that is valid PHP and doesn't clash.
     */
    public static function variable(string $value): string
    {
        $name = self::camel($value);

        return $name === '' ? 'value' : (in_array(strtolower($name), ['this', 'raw'], true) ? $name.'Value' : $name);
    }

    /**
     * An enum case name.
     */
    public static function enumCase(string $value): string
    {
        $case = self::studly($value);

        return $case === '' ? 'Empty' : (strtolower($case) === 'class' ? 'ClassValue' : $case);
    }

    /**
     * A method name from an operationId, without the resource's own nouns:
     * "list_contacts_api_v1_contacts_get" in Contacts → "list".
     *
     * @param  list<string>  $resourceWords  e.g. ["contact", "contacts"]
     */
    public static function method(string $operationId, array $resourceWords): string
    {
        $stem = (string) preg_replace('/_api_v1_.*$/', '', $operationId);
        $words = explode('_', $stem);
        $kept = array_values(array_filter($words, static fn (string $word): bool => ! in_array(strtolower($word), $resourceWords, true)));

        return self::safeMethod(self::camel(implode('_', $kept === [] ? $words : $kept)));
    }

    public static function fullMethod(string $operationId): string
    {
        return self::safeMethod(self::camel((string) preg_replace('/_api_v1_.*$/', '', $operationId)));
    }

    /**
     * @return list<string> the words of a tag, singular and plural, lower-case
     */
    public static function resourceWords(string $tag): array
    {
        $words = [];

        foreach (preg_split('/[^A-Za-z0-9]+/', strtolower($tag), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $words[] = $word;
            $words[] = str_ends_with($word, 'ies') ? substr($word, 0, -3).'y' : (str_ends_with($word, 's') ? substr($word, 0, -1) : $word.'s');
        }

        return array_values(array_unique($words));
    }

    /**
     * Words PHP doesn't allow as a class, interface or enum name.
     */
    public static function isReservedClassName(string $name): bool
    {
        return in_array(strtolower($name), [...self::RESERVED, 'enum', 'resource', 'numeric', 'scalar'], true);
    }

    public static function kebab(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }

    /**
     * Keywords are fine as method names in PHP (list(), print()…), so only
     * an empty name needs handling.
     */
    private static function safeMethod(string $name): string
    {
        return $name === '' ? 'call' : $name;
    }
}
