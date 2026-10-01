<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Maps schemas to PHP types, for responses (typed, hydrated objects) and
 * for method parameters (forgiving inputs: enums or strings, arrays for
 * nested objects).
 */
final class TypeMapper
{
    public const string ENUM_NAMESPACE = 'Reshapify\\SendSeven\\Enums';

    public const string DATA_NAMESPACE = 'Reshapify\\SendSeven\\Data';

    /** @var array<string, string> schema name → enum class */
    private array $enums = [];

    /** @var array<string, string> schema name → data class */
    private array $dataClasses = [];

    public function __construct(private readonly Spec $spec, private readonly Registry $registry) {}

    /**
     * The type of a property in a response object.
     *
     * @param  array<string, mixed>  $schema
     */
    public function response(array $schema, bool $required): PhpType
    {
        [$type, $nullable] = $this->unwrapNullable($schema);
        $description = trim((string) ($schema['description'] ?? $type['description'] ?? ''));
        $mapped = $this->responseType($type)->withDescription($description);

        $optional = $nullable || ! $required;

        if ($optional && ! in_array($mapped->native, ['array'], true)) {
            $mapped = $mapped->asNullable();
        }

        return $mapped->withHydrate($this->hydrateFor($type, $optional && $mapped->native !== 'array'));
    }

    /**
     * The type of a method parameter.
     *
     * @param  array<string, mixed>  $schema
     */
    public function parameter(array $schema, bool $required): PhpType
    {
        [$type, $nullable] = $this->unwrapNullable($schema);
        $mapped = $this->parameterType($type)->withDescription(trim((string) ($schema['description'] ?? $type['description'] ?? '')));

        return ($nullable || ! $required) ? $mapped->asNullable() : $mapped;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array{0: array<string, mixed>, 1: bool} the non-null schema and whether null is allowed
     */
    public function unwrapNullable(array $schema): array
    {
        $variants = $schema['anyOf'] ?? $schema['oneOf'] ?? null;

        if (is_array($variants)) {
            $nonNull = array_values(array_filter($variants, static fn (mixed $variant): bool => is_array($variant) && ($variant['type'] ?? null) !== 'null'));
            $nullable = count($nonNull) < count($variants);

            if (count($nonNull) === 1) {
                return [[...$nonNull[0], 'description' => $schema['description'] ?? ($nonNull[0]['description'] ?? '')], $nullable];
            }

            return [['x-union' => $nonNull, 'description' => $schema['description'] ?? ''], $nullable];
        }

        return [$schema, ($schema['nullable'] ?? false) === true];
    }

    /**
     * An object with known properties (as opposed to a free-form map).
     *
     * @param  array<string, mixed>  $schema
     */
    public function isObject(array $schema): bool
    {
        return isset($schema['properties']) && is_array($schema['properties']) && $schema['properties'] !== [];
    }

    public static function short(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * A schema that says nothing about its type ({}), or a union of types.
     *
     * @param  array<string, mixed>  $schema
     */
    private function loose(array $schema): ?PhpType
    {
        if (isset($schema['x-union'])) {
            $natives = [];

            foreach ($schema['x-union'] as $variant) {
                $natives[] = match (true) {
                    ($variant['type'] ?? null) === 'string' => 'string',
                    ($variant['type'] ?? null) === 'integer' => 'int',
                    ($variant['type'] ?? null) === 'number' => 'float',
                    ($variant['type'] ?? null) === 'boolean' => 'bool',
                    ($variant['type'] ?? null) === 'array' => 'array',
                    default => 'mixed',
                };
            }

            $natives = array_values(array_unique($natives));

            return in_array('mixed', $natives, true) ? new PhpType('mixed', 'mixed') : new PhpType(implode('|', $natives), str_replace('array', 'array<array-key, mixed>', implode('|', $natives)));
        }

        $typeless = ! isset($schema['type']) && ! isset($schema['$ref']) && ! isset($schema['properties']) && ! isset($schema['allOf']) && ! isset($schema['enum']) && ! isset($schema['additionalProperties']);

        return $typeless || isset($schema['x-mixed']) ? new PhpType('mixed', 'mixed') : null;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function responseType(array $schema): PhpType
    {
        if ($this->loose($schema) instanceof PhpType) {
            return new PhpType('mixed', 'mixed');
        }

        [$name, $resolved] = $this->spec->resolve($schema);

        if ($name !== null && isset($resolved['enum'])) {
            $class = $this->registry->enum($name, $resolved);

            return new PhpType(self::short($class).'|string', self::short($class).'|string', [$class]);
        }

        if ($name !== null && $this->isObject($resolved)) {
            $class = $this->registry->data($name);

            return new PhpType(self::short($class), self::short($class), [$class]);
        }

        return match ($resolved['type'] ?? null) {
            'string' => ($resolved['format'] ?? null) === 'date-time'
                ? new PhpType('DateTimeImmutable', 'DateTimeImmutable', ['DateTimeImmutable'])
                : new PhpType('string', isset($resolved['enum']) ? 'string' : 'string'),
            'integer' => new PhpType('int', 'int'),
            'number' => new PhpType('float', 'float'),
            'boolean' => new PhpType('bool', 'bool'),
            'array' => $this->responseList($resolved),
            default => new PhpType('array', 'array<array-key, mixed>'),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function responseList(array $schema): PhpType
    {
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        [$name, $resolved] = $items === [] ? [null, []] : $this->spec->resolve($items);

        if ($name !== null && ! isset($resolved['enum']) && $this->isObject($resolved)) {
            $class = $this->registry->data($name);

            return new PhpType('array', 'list<'.self::short($class).'>', [$class]);
        }

        $item = match ($resolved['type'] ?? null) {
            'string' => 'string',
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            default => 'mixed',
        };

        return new PhpType('array', "list<{$item}>");
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function parameterType(array $schema): PhpType
    {
        if (($loose = $this->loose($schema)) instanceof PhpType) {
            return $loose;
        }

        [$name, $resolved] = $this->spec->resolve($schema);

        if ($name !== null && isset($resolved['enum'])) {
            $class = $this->registry->enum($name, $resolved);

            return new PhpType(self::short($class).'|string', self::short($class).'|string', [$class]);
        }

        if ($name !== null && $this->isObject($resolved)) {
            return new PhpType('array', 'array<string, mixed>');
        }

        return match ($resolved['type'] ?? null) {
            'string' => match ($resolved['format'] ?? null) {
                'date-time' => new PhpType('DateTimeInterface|string', 'DateTimeInterface|string', ['DateTimeInterface']),
                'binary' => new PhpType('FilePart', 'FilePart', ['Reshapify\\SendSeven\\Http\\FilePart']),
                default => new PhpType('string', 'string'),
            },
            'integer' => new PhpType('int', 'int'),
            'number' => new PhpType('float|int', 'float|int'),
            'boolean' => new PhpType('bool', 'bool'),
            'array' => $this->parameterList($resolved),
            default => new PhpType('array', 'array<string, mixed>'),
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function parameterList(array $schema): PhpType
    {
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        [$name, $resolved] = $items === [] ? [null, []] : $this->spec->resolve($items);

        if ($name !== null && isset($resolved['enum'])) {
            $class = $this->registry->enum($name, $resolved);

            return new PhpType('array', 'list<'.self::short($class).'|string>', [$class]);
        }

        $item = match (true) {
            ($resolved['format'] ?? null) === 'binary' => 'FilePart',
            ($resolved['type'] ?? null) === 'string' => 'string',
            ($resolved['type'] ?? null) === 'integer' => 'int',
            ($resolved['type'] ?? null) === 'number' => 'float|int',
            ($resolved['type'] ?? null) === 'boolean' => 'bool',
            default => 'array<string, mixed>',
        };

        return new PhpType('array', "list<{$item}>", $item === 'FilePart' ? ['Reshapify\\SendSeven\\Http\\FilePart'] : []);
    }

    /**
     * How to read the value from Attributes ($attributes->...('%s')).
     *
     * @param  array<string, mixed>  $schema
     */
    private function hydrateFor(array $schema, bool $nullable): string
    {
        if ($this->loose($schema) instanceof PhpType) {
            return '$data[%s] ?? null';
        }

        [$name, $resolved] = $this->spec->resolve($schema);
        $n = static fn (string $method): string => $nullable ? 'nullable'.ucfirst($method) : $method;

        if ($name !== null && isset($resolved['enum'])) {
            return '$attributes->'.$n('enum').'(%s, '.self::short($this->registry->enum($name, $resolved)).'::class)';
        }

        if ($name !== null && $this->isObject($resolved)) {
            return '$attributes->'.$n('object').'(%s, '.self::short($this->registry->data($name)).'::fromArray(...))';
        }

        return match ($resolved['type'] ?? null) {
            'string' => '$attributes->'.$n(($resolved['format'] ?? null) === 'date-time' ? 'dateTime' : 'string').'(%s)',
            'integer' => '$attributes->'.$n('int').'(%s)',
            'number' => '$attributes->'.$n('float').'(%s)',
            'boolean' => '$attributes->'.$n('bool').'(%s)',
            'array' => $this->hydrateList($resolved),
            default => $nullable ? '$attributes->nullableArray(%s)' : '$attributes->array(%s)',
        };
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function hydrateList(array $schema): string
    {
        $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];
        [$name, $resolved] = $items === [] ? [null, []] : $this->spec->resolve($items);

        if ($name !== null && ! isset($resolved['enum']) && $this->isObject($resolved)) {
            return '$attributes->list(%s, '.self::short($this->registry->data($name)).'::fromArray(...))';
        }

        return match ($resolved['type'] ?? null) {
            'string' => '$attributes->strings(%s)',
            'integer' => '$attributes->ints(%s)',
            'number' => '$attributes->floats(%s)',
            default => 'array_values($attributes->array(%s))',
        };
    }
}
