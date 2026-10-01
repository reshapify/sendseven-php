<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Builds the Method model for every operation in a resource.
 */
final readonly class MethodBuilder
{
    public function __construct(private Spec $spec, private TypeMapper $types, private Registry $registry) {}

    /**
     * @param  list<Operation>  $operations
     * @return list<Method>
     */
    public function build(string $tag, array $operations): array
    {
        $words = Naming::resourceWords($tag);
        $names = [];

        foreach ($operations as $operation) {
            $names[$operation->id()] = $operation->definition['x-php-method'] ?? Naming::method($operation->id(), $words);
        }

        // A shortened name two operations share falls back to the full name.
        $counts = array_count_values($names);

        foreach ($names as $id => $name) {
            if ($counts[$name] > 1) {
                $names[$id] = Naming::fullMethod($id);
            }
        }

        return array_map(fn (Operation $operation): Method => $this->method($operation, $names[$operation->id()]), $operations);
    }

    private function method(Operation $operation, string $name): Method
    {
        $parameters = [];
        $used = [];
        $add = function (string $wire, string $in, PhpType $type, bool $required, string $description) use (&$parameters, &$used): void {
            $variable = Naming::variable($wire);

            while (isset($used[$variable])) {
                $variable .= ucfirst($in);
            }

            $used[$variable] = true;
            $parameters[] = new Parameter($variable, $wire, $in, $type, $required, $description);
        };

        $declared = [];

        foreach ($operation->parameters('path') as $parameter) {
            $declared[] = (string) $parameter['name'];
            $add((string) $parameter['name'], 'path', $this->types->parameter($parameter['schema'] ?? ['type' => 'string'], true), true, (string) ($parameter['description'] ?? ''));
        }

        // Some operations (e.g. PUT /tenants/{tenant_id}) use a path
        // placeholder without declaring it; it is still required.
        preg_match_all('/\{([^}]+)\}/', $operation->relativePath(), $placeholders);

        foreach (array_diff($placeholders[1], $declared) as $undeclared) {
            $add($undeclared, 'path', new PhpType('string', 'string'), true, '');
        }

        $body = $operation->requestBody();

        if ($body !== null) {
            [$contentType, $schema] = $body;
            [, $resolved] = $this->spec->resolve($schema);
            $in = $contentType === 'multipart/form-data' ? 'multipart' : 'body';

            if ($this->types->isObject($resolved)) {
                $required = is_array($resolved['required'] ?? null) ? $resolved['required'] : [];

                foreach ($resolved['properties'] as $field => $property) {
                    $isRequired = in_array($field, $required, true) && $operation->requestBodyRequired();
                    $type = $this->types->parameter(is_array($property) ? $property : [], $isRequired);
                    $add((string) $field, $in, $type, $isRequired, $type->description);
                }
            } else {
                $type = $this->types->parameter($schema, $operation->requestBodyRequired());
                $add('body', 'raw-body', $type, $operation->requestBodyRequired(), 'The request body');
            }
        }

        foreach ($operation->parameters('query') as $parameter) {
            $required = ($parameter['required'] ?? false) === true;
            $add((string) $parameter['name'], 'query', $this->types->parameter($parameter['schema'] ?? [], $required), $required, (string) ($parameter['description'] ?? ''));
        }

        if (in_array($operation->method, ['post', 'patch'], true)) {
            $add('idempotency_key', 'idempotency', new PhpType('?string', '?string', nullable: true), false, 'Repeat a request safely: SendSeven answers a repeat with the first result. One is generated when omitted.');
        }

        usort($parameters, static fn (Parameter $a, Parameter $b): int => ($b->required <=> $a->required));
        [$kind, $class] = $this->returnType($operation);

        return new Method($operation, $name, $parameters, $kind, $class);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function returnType(Operation $operation): array
    {
        $schema = $operation->responseSchema();

        if ($schema === null) {
            return ['void', null];
        }

        [$name, $resolved] = $this->spec->resolve($schema);
        $properties = is_array($resolved['properties'] ?? null) ? $resolved['properties'] : [];

        if (isset($properties['items'], $properties['pagination'])) {
            [$itemName, $item] = $this->spec->resolve($properties['items']['items'] ?? []);

            return $itemName !== null && $this->types->isObject($item) ? ['page', $this->registry->data($itemName)] : ['array', null];
        }

        if ($name !== null && $this->types->isObject($resolved)) {
            return ['data', $this->registry->data($name)];
        }

        if (($resolved['type'] ?? null) === 'array') {
            [$itemName, $item] = $this->spec->resolve($resolved['items'] ?? []);

            return $itemName !== null && $this->types->isObject($item) ? ['list', $this->registry->data($itemName)] : ['array', null];
        }

        return in_array($resolved['type'] ?? 'object', ['object', null], true) || isset($resolved['additionalProperties']) ? ['array', null] : ['mixed', null];
    }
}
