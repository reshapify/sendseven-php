<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Decides the PHP class for each schema the generated code uses, and
 * remembers which still need writing.
 */
final class Registry
{
    /** @var array<string, string> schema → data class (FQCN) */
    private array $data = [];

    /** @var array<string, string> schema → enum class (FQCN) */
    private array $enums = [];

    /** @var array<string, array<string, mixed>> enum class → merged schema */
    private array $enumSchemas = [];

    /** @var array<string, true> class names already taken */
    private array $taken = ['data' => true];

    /** @var list<string> schemas registered but not yet emitted */
    private array $pending = [];

    /**
     * @param  list<string>  $schemaNames  every component schema name, to avoid collisions
     * @param  list<string>  $reservedNames  class names data classes must not take (resources, runtime classes)
     */
    public function __construct(private readonly array $schemaNames, private readonly string $sourceDirectory, array $reservedNames = [])
    {
        foreach ($reservedNames as $name) {
            $this->taken[strtolower($name)] = true;
        }
    }

    public function data(string $schema): string
    {
        if (isset($this->data[$schema])) {
            return $this->data[$schema];
        }

        $name = Naming::studly($schema);
        $stripped = (string) preg_replace('/Response$/', '', $name);

        if ($stripped !== $name && $stripped !== '' && ! Naming::isReservedClassName($stripped) && ! in_array($stripped, $this->schemaNames, true) && ! isset($this->taken[strtolower($stripped)])) {
            $name = $stripped;
        }

        if (Naming::isReservedClassName($name)) {
            $name .= 'Data';
        }

        while (isset($this->taken[strtolower($name)])) {
            $name .= 'Data';
        }

        $this->taken[strtolower($name)] = true;
        $this->pending[] = $schema;

        return $this->data[$schema] = TypeMapper::DATA_NAMESPACE.'\\'.$name;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function enum(string $schemaName, array $schema): string
    {
        $name = Naming::studly($schemaName);
        $name = Naming::isReservedClassName($name) ? $name.'Type' : $name;
        $class = TypeMapper::ENUM_NAMESPACE.'\\'.$name;

        if (! isset($this->enumSchemas[$class])) {
            $this->enumSchemas[$class] = $schema;
        } else {
            // -Input and -Output variants of the same enum: keep every value.
            $this->enumSchemas[$class]['enum'] = array_values(array_unique([...$this->enumSchemas[$class]['enum'], ...$schema['enum']]));
        }

        return $this->enums[$schemaName] = $class;
    }

    /**
     * Schemas registered since the last call, to emit next.
     *
     * @return list<string>
     */
    public function takePending(): array
    {
        $pending = $this->pending;
        $this->pending = [];

        return $pending;
    }

    /**
     * Enums to write: every registered enum that isn't already hand-written.
     *
     * @return array<string, array<string, mixed>> class → schema
     */
    public function enumsToWrite(): array
    {
        $enums = [];

        foreach ($this->enumSchemas as $class => $schema) {
            if (! $this->isHandWritten($class)) {
                $enums[$class] = $schema;
            }
        }

        ksort($enums);

        return $enums;
    }

    public function isHandWritten(string $class): bool
    {
        $file = $this->fileFor($class);

        return is_file($file) && ! str_contains((string) file_get_contents($file), Writer::MARKER);
    }

    public function fileFor(string $class): string
    {
        return $this->sourceDirectory.'/'.str_replace('\\', '/', substr($class, strlen('Reshapify\\SendSeven\\'))).'.php';
    }
}
