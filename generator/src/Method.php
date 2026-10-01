<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * One generated resource method: what it is called, what it takes and what
 * it returns.
 */
final readonly class Method
{
    /**
     * @param  list<Parameter>  $parameters  in signature order
     * @param  string  $returnKind  void, data, list, page, array or mixed
     * @param  string|null  $returnClass  FQCN for data, list and page
     */
    public function __construct(
        public Operation $operation,
        public string $name,
        public array $parameters,
        public string $returnKind,
        public ?string $returnClass,
    ) {}

    public function returnNative(): string
    {
        return match ($this->returnKind) {
            'void' => 'void',
            'data' => TypeMapper::short((string) $this->returnClass),
            'page' => 'Page',
            'list', 'array' => 'array',
            default => 'mixed',
        };
    }

    public function returnDoc(): string
    {
        $short = TypeMapper::short((string) $this->returnClass);

        return match ($this->returnKind) {
            'void' => 'void',
            'data' => $short,
            'page' => "Page<{$short}>",
            'list' => "list<{$short}>",
            'array' => 'array<array-key, mixed>',
            default => 'mixed',
        };
    }

    public function hasPageParameter(): bool
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->in === 'query' && $parameter->wire === 'page') {
                return true;
            }
        }

        return false;
    }

    public function writes(): bool
    {
        return in_array($this->operation->method, ['post', 'put', 'patch', 'delete'], true);
    }

    public function sendsMultipart(): bool
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->in === 'multipart') {
                return true;
            }
        }

        return false;
    }
}
