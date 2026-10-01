<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

use RuntimeException;

/**
 * SendSeven's OpenAPI document with our verified patches applied.
 */
final class Spec
{
    public const string PATH_PREFIX = '/api/v1';

    /** @var array<string, mixed> */
    private array $document;

    /**
     * @param  list<string>  $patchFiles
     */
    public function __construct(string $specFile, array $patchFiles = [])
    {
        $this->document = self::read($specFile);

        foreach ($patchFiles as $file) {
            $this->applyPatch(self::read($file));
        }
    }

    public function version(): string
    {
        return (string) ($this->document['info']['version'] ?? 'unknown');
    }

    /**
     * Every operation, grouped by its first tag, sorted for stable output.
     *
     * @return array<string, list<Operation>>
     */
    public function operationsByTag(): array
    {
        $groups = [];

        foreach ($this->document['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (! is_array($operation) || ! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $tag = $operation['tags'][0] ?? 'General';

                // Tags that differ only in spelling ("KB Health", "Kb Health") are one resource.
                foreach (array_keys($groups) as $existing) {
                    if (Naming::studly((string) $existing) === Naming::studly($tag)) {
                        $tag = (string) $existing;
                    }
                }

                $groups[$tag][] = new Operation($this, (string) $path, $method, $operation);
            }
        }

        ksort($groups);

        foreach ($groups as &$operations) {
            usort($operations, static fn (Operation $a, Operation $b): int => [$a->path, $a->method] <=> [$b->path, $b->method]);
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function schema(string $name): ?array
    {
        $schema = $this->document['components']['schemas'][$name] ?? null;

        return is_array($schema) ? $schema : null;
    }

    /**
     * Follow a $ref (and single-item allOf) to the schema it names.
     *
     * @param  array<string, mixed>  $schema
     * @return array{0: ?string, 1: array<string, mixed>} the component name, if any, and the schema
     */
    public function resolve(array $schema): array
    {
        if (isset($schema['allOf']) && is_array($schema['allOf']) && count($schema['allOf']) === 1) {
            return $this->resolve($schema['allOf'][0]);
        }

        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $name = substr($schema['$ref'], strrpos($schema['$ref'], '/') + 1);

            return [$name, $this->schema($name) ?? throw new RuntimeException("Unknown schema {$name}")];
        }

        return [null, $schema];
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function applyPatch(array $patch): void
    {
        foreach ($patch['paths'] ?? [] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $this->document['paths'][$path][$method] = array_replace_recursive($this->document['paths'][$path][$method] ?? [], $operation);
            }
        }

        foreach ($patch['schemas'] ?? [] as $name => $schema) {
            $this->document['components']['schemas'][$name] = array_replace_recursive($this->document['components']['schemas'][$name] ?? [], $schema);
        }

        foreach ($patch['operations'] ?? [] as $operationId => $changes) {
            foreach ($this->document['paths'] as $path => $methods) {
                foreach ($methods as $method => $operation) {
                    if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
                        $this->document['paths'][$path][$method] = array_replace_recursive($operation, $changes);
                    }
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : throw new RuntimeException("{$file} is not a JSON object");
    }
}
