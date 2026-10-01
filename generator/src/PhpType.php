<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * A PHP type for a schema: the native declaration, the richer PHPDoc type,
 * and the classes it needs imported.
 */
final readonly class PhpType
{
    /**
     * @param  list<string>  $imports  fully qualified class names
     */
    public function __construct(
        public string $native,
        public string $doc,
        public array $imports = [],
        public bool $nullable = false,
        /** response side: how to read it from Attributes, with %s for the key */
        public string $hydrate = '',
        /** the schema's description, for docs */
        public string $description = '',
    ) {}

    public function asNullable(): self
    {
        if ($this->nullable || $this->native === 'mixed') {
            return new self($this->native, $this->doc, $this->imports, true, $this->hydrate, $this->description);
        }

        $native = str_contains($this->native, '|') ? $this->native.'|null' : '?'.$this->native;
        $doc = str_contains($this->doc, '|') || str_contains($this->doc, '<') ? $this->doc.'|null' : '?'.$this->doc;

        return new self($native, $doc, $this->imports, true, $this->hydrate, $this->description);
    }

    public function withHydrate(string $hydrate): self
    {
        return new self($this->native, $this->doc, $this->imports, $this->nullable, $hydrate, $this->description);
    }

    public function withDescription(string $description): self
    {
        return new self($this->native, $this->doc, $this->imports, $this->nullable, $this->hydrate, $description);
    }

    public function needsDoc(): bool
    {
        return ltrim($this->doc, '?') !== ltrim($this->native, '?');
    }
}
