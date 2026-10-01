<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Writes one class per tag with a method per operation.
 */
final readonly class ResourceEmitter
{
    public const string NAMESPACE = 'Reshapify\\SendSeven\\Resources';

    public function __construct(private string $sourceDirectory) {}

    /**
     * @param  list<Method>  $methods
     */
    public function emit(string $tag, array $methods): string
    {
        $class = Naming::studly($tag);
        $imports = [
            'Reshapify\\SendSeven\\Http\\Connector',
            'Reshapify\\SendSeven\\Http\\Method',
            'Reshapify\\SendSeven\\Http\\Request',
            'Reshapify\\SendSeven\\Exceptions\\ApiException',
        ];
        $bodies = [];

        foreach ($methods as $method) {
            $bodies[] = $this->method($method, $imports);
        }

        $helpers = self::NAMESPACE.'\\Concerns\\'.$class.'Helpers';
        $usesHelpers = is_file($this->sourceDirectory.'/Resources/Concerns/'.$class.'Helpers.php');

        if ($usesHelpers) {
            $imports[] = $helpers;
        }

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace ".self::NAMESPACE.";\n\n"
            .Writer::header()
            .Code::uses($imports, self::NAMESPACE)
            .Code::docblock(["{$tag}: ".count($methods).' endpoint'.(count($methods) === 1 ? '' : 's').'.', '', 'Reach it with $sendseven->'.lcfirst($class).'().', '', '@see https://api.sendseven.com/api/v1/docs#/'.rawurlencode($tag)])
            ."final readonly class {$class}\n{\n"
            .($usesHelpers ? '    use '.TypeMapper::short($helpers).";\n\n" : '')
            ."    public function __construct(private Connector \$connector) {}\n\n"
            .implode("\n", $bodies)
            ."}\n";
    }

    /**
     * @param  list<string>  $imports  added to
     */
    private function method(Method $method, array &$imports): string
    {
        $operation = $method->operation;
        $doc = [$operation->summary() === '' ? Naming::studly($method->name).'.' : rtrim($operation->summary(), '.').'.', ''];
        array_push($doc, ...Code::prose($operation->description()));

        $requirements = [];

        if ($operation->scopes() !== []) {
            $requirements[] = 'Scopes: '.implode(', ', $operation->scopes()).'.';
        }

        if ($operation->requiredFeature() !== null) {
            $requirements[] = "Needs the {$operation->requiredFeature()} feature on the SendSeven plan.";
        }

        if ($operation->requiredRole() === 'billing_account_owner') {
            $requirements[] = "Needs a token from the billing account's owner.";
        }

        if ($requirements !== []) {
            array_push($doc, implode(' ', $requirements), '');
        }

        foreach ($method->parameters as $parameter) {
            array_push($imports, ...$parameter->type->imports);
            $description = $parameter->description === '' ? '' : '  '.(string) preg_replace('/\s+/', ' ', $parameter->description);
            $doc[] = "@param  {$parameter->type->doc}  \${$parameter->name}{$description}";
        }

        if ($method->returnKind === 'page') {
            $imports[] = 'Reshapify\\SendSeven\\Pagination\\Page';
        }

        if ($method->returnClass !== null) {
            $imports[] = $method->returnClass;
        }

        if ($method->returnNative() !== $method->returnDoc()) {
            $doc[] = '@return '.$method->returnDoc();
        }

        if ($method->parameters !== [] || $method->returnNative() !== $method->returnDoc()) {
            $doc[] = '';
        }

        $doc[] = '@throws ApiException';
        $doc[] = '';
        $doc[] = '@see '.$operation->referenceUrl();

        if ($operation->isDeprecated()) {
            $doc[] = '@deprecated SendSeven marks this endpoint as deprecated.';
        }

        $signature = implode(', ', array_map(static fn (Parameter $parameter): string => $parameter->signature(), $method->parameters));

        return Code::docblock($doc, '    ')
            ."    public function {$method->name}({$signature}): {$method->returnNative()}\n    {\n"
            .$this->body($method, $imports)
            ."    }\n";
    }

    /**
     * @param  list<string>  $imports
     */
    private function body(Method $method, array &$imports): string
    {
        $operation = $method->operation;
        $byLocation = [];

        foreach ($method->parameters as $parameter) {
            $byLocation[$parameter->in][] = $parameter;
        }

        $arguments = ['Method::'.ucfirst($operation->method), $this->path($operation->relativePath(), $byLocation['path'] ?? [])];

        if (isset($byLocation['path'])) {
            $imports[] = 'Reshapify\\SendSeven\\Support\\Payload';
        }

        if (isset($byLocation['query'])) {
            $imports[] = 'Reshapify\\SendSeven\\Support\\Payload';
            $arguments[] = 'query: Payload::query('.$this->fields($byLocation['query']).')';
        }

        if (isset($byLocation['body'])) {
            $imports[] = 'Reshapify\\SendSeven\\Support\\Payload';
            $arguments[] = 'body: Payload::body('.$this->fields($byLocation['body']).')';
        } elseif (isset($byLocation['raw-body'])) {
            $arguments[] = 'body: $body';
        }

        if (isset($byLocation['idempotency'])) {
            $arguments[] = "headers: \$idempotencyKey === null ? [] : ['Idempotency-Key' => \$idempotencyKey]";
        }

        if (isset($byLocation['multipart'])) {
            $imports[] = 'Reshapify\\SendSeven\\Support\\Payload';
            $arguments[] = 'multipart: Payload::multipart('.$this->fields($byLocation['multipart']).')';
        }

        $send = "\$this->connector->send(new Request(\n            ".implode(",\n            ", $arguments).",\n        ))";
        $short = TypeMapper::short((string) $method->returnClass);

        switch ($method->returnKind) {
            case 'void':
                return "        {$send};\n";
            case 'data':
                return "        \$response = {$send};\n\n        return {$short}::fromArray(\$response->data());\n";
            case 'list':
                $imports[] = 'Reshapify\\SendSeven\\Support\\Hydrate';

                return "        \$response = {$send};\n\n        return Hydrate::list(\$response->data(), {$short}::fromArray(...));\n";
            case 'page':
                $imports[] = 'Reshapify\\SendSeven\\Support\\Hydrate';
                $fetch = 'null';

                if ($method->hasPageParameter()) {
                    $call = implode(', ', array_map(
                        static fn (Parameter $parameter): string => $parameter->name.': '.($parameter->in === 'query' && $parameter->wire === 'page' ? '$page' : '$'.$parameter->name),
                        $method->parameters,
                    ));
                    $fetch = "fn (int \$page): Page => \$this->{$method->name}({$call})";
                }

                return "        \$response = {$send};\n\n        return Hydrate::page(\$response->data(), {$short}::fromArray(...), {$fetch});\n";
            case 'array':
                return "        \$response = {$send};\n\n        return \$response->data();\n";
            default:
                return "        \$response = {$send};\n\n        return \$response->decoded();\n";
        }
    }

    /**
     * @param  list<Parameter>  $parameters
     */
    private function path(string $template, array $parameters): string
    {
        $byWire = [];

        foreach ($parameters as $parameter) {
            $byWire[$parameter->wire] = $parameter->name;
        }

        $pieces = preg_split('/(\{[^}]+\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$template];
        $parts = [];

        foreach ($pieces as $piece) {
            $parts[] = preg_match('/^\{(.+)\}$/', $piece, $match) === 1 && isset($byWire[$match[1]])
                ? 'Payload::segment($'.$byWire[$match[1]].')'
                : Code::string($piece);
        }

        return implode('.', $parts);
    }

    /**
     * @param  list<Parameter>  $parameters
     */
    private function fields(array $parameters): string
    {
        return '['.implode(', ', array_map(static fn (Parameter $parameter): string => Code::string($parameter->wire).' => $'.$parameter->name, $parameters)).']';
    }
}
