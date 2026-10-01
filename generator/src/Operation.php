<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * One API operation, with the bits of its OpenAPI definition the generator
 * needs.
 */
final readonly class Operation
{
    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        public Spec $spec,
        public string $path,
        public string $method,
        public array $definition,
    ) {}

    public function id(): string
    {
        return (string) $this->definition['operationId'];
    }

    /**
     * The path below the base URI, e.g. /contacts/{contact_id}.
     */
    public function relativePath(): string
    {
        return str_starts_with($this->path, Spec::PATH_PREFIX) ? substr($this->path, strlen(Spec::PATH_PREFIX)) : $this->path;
    }

    public function summary(): string
    {
        return trim((string) ($this->definition['summary'] ?? ''));
    }

    /**
     * The description without the scopes line (reported separately).
     */
    public function description(): string
    {
        $text = (string) ($this->definition['description'] ?? '');
        $text = (string) preg_replace('/\*\*[^*]*Required Scopes?:\*\*[^\n]*/u', '', $text);

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        preg_match_all('/Required Scopes?:\*\*\s*([^\n]+)/', (string) ($this->definition['description'] ?? ''), $lines);
        $scopes = [];

        foreach ($lines[1] as $line) {
            preg_match_all('/`([^`]+)`/', $line, $matches);
            array_push($scopes, ...$matches[1]);
        }

        return array_values(array_unique($scopes));
    }

    public function requiredFeature(): ?string
    {
        $feature = $this->definition['x-requires-feature'] ?? null;

        return is_string($feature) ? $feature : null;
    }

    public function requiredRole(): ?string
    {
        $role = $this->definition['x-requires-role'] ?? null;

        return is_string($role) ? $role : null;
    }

    public function source(): ?string
    {
        $source = $this->definition['x-source'] ?? null;

        return is_string($source) ? $source : null;
    }

    public function isDeprecated(): bool
    {
        return ($this->definition['deprecated'] ?? false) === true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parameters(string $in): array
    {
        return array_values(array_filter(
            $this->definition['parameters'] ?? [],
            static fn (mixed $parameter): bool => is_array($parameter) && ($parameter['in'] ?? null) === $in,
        ));
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null content type and schema
     */
    public function requestBody(): ?array
    {
        foreach (['application/json', 'multipart/form-data'] as $type) {
            $schema = $this->definition['requestBody']['content'][$type]['schema'] ?? null;

            if (is_array($schema)) {
                return [$type, $schema];
            }
        }

        return null;
    }

    public function requestBodyRequired(): bool
    {
        return ($this->definition['requestBody']['required'] ?? false) === true;
    }

    /**
     * The success response's JSON schema; null when it has no body.
     *
     * @return array<string, mixed>|null
     */
    public function responseSchema(): ?array
    {
        foreach ($this->definition['responses'] ?? [] as $status => $response) {
            if (str_starts_with((string) $status, '2')) {
                $schema = $response['content']['application/json']['schema'] ?? null;

                return is_array($schema) && $schema !== [] ? $schema : null;
            }
        }

        return null;
    }

    public function referenceUrl(): string
    {
        $tag = rawurlencode((string) ($this->definition['tags'][0] ?? ''));

        return "https://api.sendseven.com/api/v1/docs#/{$tag}/{$this->id()}";
    }
}
