<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Generator;

/**
 * Writes the reference docs (one page per resource) and the files agents
 * read first: llms.txt (an index) and llms-full.txt (everything).
 */
final class DocsEmitter
{
    /**
     * @param  list<Method>  $methods
     */
    public function resource(string $tag, array $methods): string
    {
        $accessor = lcfirst(Naming::studly($tag));
        $out = ["# {$tag}", '', "`\$sendseven->{$accessor}()` — ".count($methods).' endpoint'.(count($methods) === 1 ? '' : 's').'.', ''];
        $out[] = '| Method | HTTP | Summary |';
        $out[] = '|---|---|---|';

        foreach ($methods as $method) {
            $out[] = "| [`{$method->name}()`](#".strtolower($method->name).') | `'.strtoupper($method->operation->method).' '.$method->operation->relativePath().'` | '.$this->cell($method->operation->summary()).' |';
        }

        foreach ($methods as $method) {
            array_push($out, '', ...$this->method($accessor, $method));
        }

        return Writer::markdownHeader().implode("\n", $out)."\n";
    }

    /**
     * @param  array<string, list<Method>>  $resources
     */
    public function index(array $resources, string $specVersion): string
    {
        $total = array_sum(array_map('count', $resources));
        $out = ['# API reference', '', "Every SendSeven endpoint ({$total}), generated from SendSeven's OpenAPI spec (version {$specVersion}) with our verified corrections in `openapi/patches`.", '', '| Resource | Accessor | Endpoints |', '|---|---|---|'];

        foreach ($resources as $tag => $methods) {
            $out[] = "| [{$tag}](".Naming::kebab($tag).'.md) | `$sendseven->'.lcfirst(Naming::studly($tag)).'()` | '.count($methods).' |';
        }

        return Writer::markdownHeader().implode("\n", $out)."\n";
    }

    /**
     * @param  array<string, list<Method>>  $resources
     */
    public function llms(array $resources): string
    {
        $out = [
            '# reshapify/sendseven',
            '',
            '> A typed PHP SDK for the SendSeven messaging API (WhatsApp, SMS, email, Telegram, Messenger, Instagram, RCS, browser push). Covers every endpoint; webhooks are verified and parsed into typed events.',
            '',
            'Start here:',
            '',
            '- [README](README.md): install, the client, sending a message, receiving webhooks, errors, testing',
            '- [AGENTS.md](AGENTS.md): rules for agents using or changing this SDK',
            '- [Known quirks](docs/known-quirks.md): where SendSeven behaves differently from its spec',
            '- [openapi/manifest.json](openapi/manifest.json): every endpoint mapped to its SDK call, parameters and return type',
            '- [llms-full.txt](llms-full.txt): all of the docs and the full reference in one file (large; search it rather than reading it whole)',
            '',
            '## Guides',
            '',
            '- [Webhooks](docs/guides/webhooks.md): the verification challenge, signatures, events',
            '- [Onboarding channels](docs/guides/onboarding-channels.md): connect links, WhatsApp Embedded Signup, Coexistence',
            '- [Tenancy and plans](docs/guides/tenancy.md): single tenant vs partner, multi_tenant, billing-account owner',
            '- [Channels and identifiers](docs/guides/channels.md): phone numbers vs scoped IDs, reply windows, RCS',
            '- [Rate limits, retries and idempotency](docs/guides/resilience.md)',
            '- [Testing](docs/guides/testing.md): SendSeven::fake() and signed webhook fixtures',
            '',
            '## API reference',
            '',
        ];

        foreach ($resources as $tag => $methods) {
            $out[] = "- [{$tag}](docs/reference/".Naming::kebab($tag).'.md): '.implode(', ', array_map(static fn (Method $method): string => $method->name.'()', $methods));
        }

        return Writer::markdownHeader().implode("\n", $out)."\n";
    }

    /**
     * Everything in one file, for agents that read a single document: the
     * hand-written docs first, then every reference page.
     *
     * @param  array<string, string>  $handWritten  contents keyed by repository path
     * @param  array<string, list<Method>>  $resources
     */
    public function llmsFull(array $handWritten, array $resources): string
    {
        $sections = [];

        foreach ($handWritten as $path => $contents) {
            $sections[] = "<!-- {$path} -->\n\n".trim($contents);
        }

        foreach ($resources as $tag => $methods) {
            $page = 'docs/reference/'.Naming::kebab($tag).'.md';
            $sections[] = "<!-- {$page} -->\n\n".trim(str_replace(Writer::markdownHeader(), '', $this->resource($tag, $methods)));
        }

        return Writer::markdownHeader().implode("\n\n---\n\n", $sections)."\n";
    }

    /**
     * @return list<string>
     */
    private function method(string $accessor, Method $method): array
    {
        $operation = $method->operation;
        $out = ["## {$method->name}", ''];
        $out[] = ($operation->summary() === '' ? '' : rtrim($operation->summary(), '.').'. ').'`'.strtoupper($operation->method).' '.$operation->relativePath().'`';
        $out[] = '';

        foreach (Code::prose($operation->description(), 2) as $line) {
            $out[] = $line;
        }

        $out[] = '```php';
        $out[] = $this->example($accessor, $method);
        $out[] = '```';
        $out[] = '';

        $requirements = array_filter([
            $operation->scopes() === [] ? null : 'Scopes: `'.implode('`, `', $operation->scopes()).'`',
            $operation->requiredFeature() === null ? null : "Plan feature: `{$operation->requiredFeature()}`",
            $operation->requiredRole() === 'billing_account_owner' ? "Role: the billing account's owner" : null,
            $operation->isDeprecated() ? '**Deprecated** by SendSeven' : null,
            $operation->source() === null ? null : 'Not in SendSeven\'s spec; verified: '.$operation->source(),
        ]);

        foreach ($requirements as $requirement) {
            $out[] = "- {$requirement}";
        }

        if ($requirements !== []) {
            $out[] = '';
        }

        if ($method->parameters !== []) {
            $out[] = '| Parameter | Type | Required | Description |';
            $out[] = '|---|---|---|---|';

            foreach ($method->parameters as $parameter) {
                $out[] = "| `\${$parameter->name}` | `".str_replace('|', '\\|', $parameter->type->doc).'` | '.($parameter->required ? 'yes' : 'no').' | '.$this->cell($parameter->description).' |';
            }

            $out[] = '';
        }

        $out[] = 'Returns `'.str_replace('|', '\\|', $method->returnDoc()).'`. [SendSeven reference]('.$operation->referenceUrl().')';

        return $out;
    }

    private function example(string $accessor, Method $method): string
    {
        $arguments = [];

        foreach ($method->parameters as $parameter) {
            if (! $parameter->required) {
                continue;
            }

            $type = ltrim($parameter->type->native, '?');
            $value = match (true) {
                str_starts_with($type, 'int') => '1',
                str_starts_with($type, 'float') => '1.0',
                $type === 'bool' => 'true',
                $type === 'array' => '[]',
                $type === 'FilePart' => "FilePart::fromPath('/path/to/file.pdf')",
                $type === 'mixed' => "'...'",
                default => "'".$parameter->wire."'",
            };
            $arguments[] = "{$parameter->name}: {$value}";
        }

        $call = "\$sendseven->{$accessor}()->{$method->name}(".implode(', ', $arguments).');';

        return $method->returnKind === 'void' ? $call : '$result = '.$call;
    }

    private function cell(string $text): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '`'], '', $text))));
    }
}
