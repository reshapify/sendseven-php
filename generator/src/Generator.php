<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Generates the resources, response objects, enums, client accessors and
 * reference docs from the spec.
 */
final readonly class Generator
{
    /** Names the runtime uses, which generated data classes must not take. */
    private const array RESERVED_CLASS_NAMES = [
        'Request', 'Response', 'Method', 'Page', 'Pagination', 'Payload', 'Hydrate', 'Connector', 'Attributes',
        'Data', 'FilePart', 'Client', 'Factory', 'ApiException',
    ];

    public function __construct(private string $root) {}

    /**
     * @return array{resources: int, methods: int, data: int, enums: int, removed: int}
     */
    public function run(): array
    {
        $spec = new Spec($this->root.'/openapi/sendseven.json', glob($this->root.'/openapi/patches/*.json') ?: []);
        $schemaNames = array_keys($this->allSchemas($this->root.'/openapi/sendseven.json'));
        $operationsByTag = $spec->operationsByTag();
        $resourceNames = array_map(Naming::studly(...), array_keys($operationsByTag));

        $registry = new Registry($schemaNames, $this->root.'/src', [...$resourceNames, ...self::RESERVED_CLASS_NAMES]);
        $types = new TypeMapper($spec, $registry);
        $builder = new MethodBuilder($spec, $types, $registry);
        $writer = new Writer($this->root);
        $docs = new DocsEmitter;
        $resources = new ResourceEmitter($this->root.'/src');

        $methodsByTag = [];

        foreach ($operationsByTag as $tag => $operations) {
            $methodsByTag[$tag] = $builder->build($tag, $operations);
        }

        foreach ($methodsByTag as $tag => $methods) {
            $writer->write('src/Resources/'.Naming::studly($tag).'.php', $resources->emit($tag, $methods));
            $writer->write('docs/reference/'.Naming::kebab($tag).'.md', $docs->resource($tag, $methods));
        }

        $dataEmitter = new DataEmitter($spec, $types, $registry);
        $dataCount = 0;

        while (($pending = $registry->takePending()) !== []) {
            foreach ($pending as $schemaName) {
                $class = $registry->data($schemaName);
                $writer->write('src/Data/'.TypeMapper::short($class).'.php', $dataEmitter->emit($schemaName));
                $dataCount++;
            }
        }

        $enumEmitter = new EnumEmitter;
        $enums = $registry->enumsToWrite();

        foreach ($enums as $class => $schema) {
            $writer->write('src/Enums/'.TypeMapper::short($class).'.php', $enumEmitter->emit($class, $schema));
        }

        $writer->write('src/Resources/Concerns/ProvidesResources.php', (new ClientEmitter)->emit(array_map('count', $methodsByTag)));
        $writer->write('docs/reference/README.md', $docs->index($methodsByTag, $spec->version()));
        $writer->write('llms.txt', $docs->llms($methodsByTag));
        $writer->write('llms-full.txt', $docs->llmsFull($this->handWrittenDocs(), $methodsByTag));
        $writer->writeJson('openapi/manifest.json', $this->manifest($methodsByTag, $spec->version()));

        $removed = $writer->prune(['src/Resources', 'src/Data', 'src/Enums', 'docs/reference']);

        return [
            'resources' => count($methodsByTag),
            'methods' => array_sum(array_map('count', $methodsByTag)),
            'data' => $dataCount,
            'enums' => count($enums),
            'removed' => $removed,
        ];
    }

    /**
     * Every operation and the SDK method that calls it: for agents looking up
     * an endpoint, and for the contract test that checks each one.
     *
     * @param  array<string, list<Method>>  $methodsByTag
     * @return array<string, mixed>
     */
    private function manifest(array $methodsByTag, string $specVersion): array
    {
        $operations = [];

        foreach ($methodsByTag as $tag => $methods) {
            foreach ($methods as $method) {
                $operations[] = [
                    'operationId' => $method->operation->id(),
                    'call' => '$sendseven->'.lcfirst(Naming::studly($tag)).'()->'.$method->name.'()',
                    'resource' => lcfirst(Naming::studly($tag)),
                    'method' => $method->name,
                    'http' => strtoupper($method->operation->method).' '.$method->operation->relativePath(),
                    'summary' => $method->operation->summary(),
                    'scopes' => $method->operation->scopes(),
                    'returns' => $method->returnDoc(),
                    'parameters' => array_map(static fn (Parameter $parameter): array => [
                        'name' => $parameter->name,
                        'wire' => $parameter->wire,
                        'in' => $parameter->in,
                        'type' => $parameter->type->native,
                        'required' => $parameter->required,
                    ], $method->parameters),
                ];
            }
        }

        return ['specVersion' => $specVersion, 'operations' => $operations];
    }

    /**
     * The hand-written docs, in reading order, keyed by repository path.
     *
     * @return array<string, string>
     */
    private function handWrittenDocs(): array
    {
        $paths = ['README.md', 'AGENTS.md', 'docs/known-quirks.md'];

        foreach (glob($this->root.'/docs/guides/*.md') ?: [] as $guide) {
            $paths[] = 'docs/guides/'.basename($guide);
        }

        $docs = [];

        foreach ($paths as $path) {
            if (is_file($this->root.'/'.$path)) {
                $docs[$path] = (string) file_get_contents($this->root.'/'.$path);
            }
        }

        return $docs;
    }

    /**
     * @return array<string, mixed>
     */
    private function allSchemas(string $file): array
    {
        $document = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return is_array($document['components']['schemas'] ?? null) ? $document['components']['schemas'] : [];
    }
}
