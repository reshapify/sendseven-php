<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

final class EnumEmitter
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function emit(string $class, array $schema): string
    {
        $short = TypeMapper::short($class);
        $cases = [];
        $used = [];

        foreach ($schema['enum'] as $value) {
            $case = Naming::enumCase((string) $value);
            $base = $case;
            $counter = 2;

            while (isset($used[strtolower($case)])) {
                $case = $base.$counter++;
            }

            $used[strtolower($case)] = true;
            $cases[] = "    case {$case} = ".Code::string((string) $value).';';
        }

        $title = Code::prose((string) ($schema['description'] ?? ''), 1);
        $doc = [...($title === [] ? ["{$short} values from the SendSeven API.", ''] : $title), 'SendSeven may add values: responses carry unknown ones as plain strings.'];

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace ".TypeMapper::ENUM_NAMESPACE.";\n\n"
            .Writer::header()
            .Code::docblock($doc)
            ."enum {$short}: string\n{\n".implode("\n", $cases)."\n}\n";
    }
}
