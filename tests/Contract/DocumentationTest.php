<?php

declare(strict_types=1);

use Reshapify\SendSeven\Client;

/*
 * Every SDK call written in the README and guides exists, with the named
 * arguments it uses. Keeps the docs honest as the API is regenerated.
 */

/**
 * @return iterable<string, array{0: string, 1: string, 2: list<string>}>
 */
function documentedCalls(): iterable
{
    $files = [__DIR__.'/../../README.md', __DIR__.'/../../AGENTS.md', __DIR__.'/../../skills/sendseven/SKILL.md', ...glob(__DIR__.'/../../docs/guides/*.md') ?: []];

    foreach ($files as $file) {
        $text = (string) file_get_contents($file);
        // $sendseven->contacts()->list(...), $fake->client->..., $acme->...
        preg_match_all('/\$\w+(?:->client)?(?:->forTenant\([^)]*\))?->(\w+)\(\)->(\w+)\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/s', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            yield basename($file).': '.$match[1].'()->'.$match[2].'()' => [$match[1], $match[2], topLevelNamedArguments($match[3])];
        }

        // Inline mentions: `whatsApp()->getCoexistenceStatus($channelId)`
        preg_match_all('/`(\w+)\(\)->(\w+)\(([^`]*)\)`/', $text, $inline, PREG_SET_ORDER);

        foreach ($inline as $match) {
            yield basename($file).': inline '.$match[1].'()->'.$match[2].'()' => [$match[1], $match[2], topLevelNamedArguments($match[3])];
        }
    }
}

/**
 * Named arguments of the call itself, not of calls nested inside it.
 *
 * @return list<string>
 */
function topLevelNamedArguments(string $arguments): array
{
    $depth = 0;
    $topLevel = '';

    foreach (str_split($arguments) as $character) {
        $depth += match ($character) {
            '(', '[' => 1,
            ')', ']' => -1,
            default => 0,
        };

        $topLevel .= $depth === 0 ? $character : ' ';
    }

    preg_match_all('/(?<![\w>$])(\w+):\s/', $topLevel, $named);

    return array_values(array_unique($named[1]));
}

it('documents only calls that exist', function (string $resource, string $method, array $arguments): void {
    $client = new ReflectionClass(Client::class);

    expect($client->hasMethod($resource))->toBeTrue("Client has no {$resource}()");

    $resourceClass = (string) $client->getMethod($resource)->getReturnType();

    if (! class_exists($resourceClass)) {
        return; // e.g. forTenant()
    }

    $reflection = new ReflectionClass($resourceClass);
    expect($reflection->hasMethod($method))->toBeTrue("{$resourceClass} has no {$method}()");

    $parameters = array_map(fn (ReflectionParameter $parameter): string => $parameter->getName(), $reflection->getMethod($method)->getParameters());

    foreach ($arguments as $argument) {
        expect($parameters)->toContain($argument);
    }
})->with(documentedCalls());
