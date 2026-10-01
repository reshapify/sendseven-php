<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Writes the trait that gives Client one accessor per resource.
 */
final class ClientEmitter
{
    /**
     * @param  array<string, int>  $resources  tag → number of endpoints
     */
    public function emit(array $resources): string
    {
        $imports = [];
        $methods = [];

        foreach ($resources as $tag => $count) {
            $class = Naming::studly($tag);
            $imports[] = ResourceEmitter::NAMESPACE.'\\'.$class;
            $methods[] = Code::docblock(["{$tag} ({$count} endpoint".($count === 1 ? '' : 's').').'], '    ')
                .'    public function '.lcfirst($class)."(): {$class}\n    {\n        return new {$class}(\$this->connector);\n    }\n";
        }

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace ".ResourceEmitter::NAMESPACE."\\Concerns;\n\n"
            .Writer::header()
            .Code::uses($imports, ResourceEmitter::NAMESPACE.'\\Concerns')
            .Code::docblock(['One accessor per area of the SendSeven API.', '', '@internal used by Client'])
            ."trait ProvidesResources\n{\n".implode("\n", $methods)."}\n";
    }
}
