<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Writes a final readonly class for each response object.
 */
final readonly class DataEmitter
{
    public function __construct(private Spec $spec, private TypeMapper $types, private Registry $registry) {}

    public function emit(string $schemaName): string
    {
        $schema = $this->spec->schema($schemaName) ?? [];
        $class = $this->registry->data($schemaName);
        $short = TypeMapper::short($class);
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $imports = ['Reshapify\\SendSeven\\Support\\Attributes'];
        $parameters = [];
        $parameterDocs = [];
        $assignments = [];
        $used = ['raw' => true];

        foreach ($schema['properties'] ?? [] as $key => $property) {
            $type = $this->types->response(is_array($property) ? $property : [], in_array($key, $required, true));
            array_push($imports, ...$type->imports);
            $name = Naming::variable((string) $key);

            while (isset($used[$name])) {
                $name .= 'Value';
            }

            $used[$name] = true;
            $description = (string) preg_replace('/\s+/', ' ', $type->description);
            $parameterDocs[] = "@param  {$type->doc}  \${$name}".($description === '' ? '' : "  {$description}");
            $parameters[] = "        public {$type->native} \${$name},";
            $hydrate = str_contains($type->hydrate, '$data[%s]')
                ? str_replace('%s', Code::string((string) $key), $type->hydrate)
                : sprintf($type->hydrate, Code::string((string) $key));
            $assignments[] = "            {$name}: {$hydrate},";
        }

        $description = Code::prose((string) ($schema['description'] ?? ''), 2);
        $title = $description === [] ? [Naming::studly($schemaName).' from the SendSeven API.', ''] : $description;

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace ".TypeMapper::DATA_NAMESPACE.";\n\n"
            .Writer::header()
            .Code::uses($imports, TypeMapper::DATA_NAMESPACE)
            .Code::docblock([...$title, "@see https://api.sendseven.com/api/v1/docs (schema {$schemaName})"])
            ."final readonly class {$short} extends Data\n{\n"
            .Code::docblock([...$parameterDocs, '@param  array<array-key, mixed>  $raw'], '    ')
            ."    public function __construct(\n".implode("\n", $parameters).($parameters === [] ? '' : "\n")."        array \$raw = [],\n    ) {\n        parent::__construct(\$raw);\n    }\n\n"
            ."    /**\n     * @param  array<array-key, mixed>  \$data\n     */\n"
            .'    public static function fromArray(array $data, string $path = '.Code::string(lcfirst($short))."): self\n    {\n"
            ."        \$attributes = new Attributes(\$data, \$path);\n\n"
            ."        return new self(\n".implode("\n", $assignments).($assignments === [] ? '' : "\n")."            raw: \$data,\n        );\n    }\n}\n";
    }
}
