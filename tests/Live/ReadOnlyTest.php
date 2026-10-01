<?php

declare(strict_types=1);

use Reshapify\SendSeven\Account\Capabilities;
use Reshapify\SendSeven\Client;
use Reshapify\SendSeven\Pagination\Page;
use Reshapify\SendSeven\SendSeven;

/*
 * Read-only calls against the real API, to catch drift between SendSeven's
 * responses and the typed objects. Opt in with a token:
 *
 *     SENDSEVEN_TEST_TOKEN=s7_api_... vendor/bin/pest --group=live
 *
 * Nothing here creates, changes or deletes anything.
 */

beforeEach(function (): void {
    if (! is_string(getenv('SENDSEVEN_TEST_TOKEN')) || getenv('SENDSEVEN_TEST_TOKEN') === '') {
        $this->markTestSkipped('Set SENDSEVEN_TEST_TOKEN to run the live tests.');
    }
});

function liveClient(): Client
{
    return SendSeven::client((string) getenv('SENDSEVEN_TEST_TOKEN'));
}

it('hydrates live responses', function (Closure $call): void {
    $result = $call(liveClient());

    if ($result instanceof Page) {
        iterator_to_array($result);
    }

    expect($result)->not->toBeNull();
})->with([
    'tenants()->mine()' => fn (Client $sendseven): mixed => $sendseven->tenants()->mine(),
    'tenants()->features()' => fn (Client $sendseven): mixed => $sendseven->tenants()->features(),
    'tenants()->retention()' => fn (Client $sendseven): mixed => $sendseven->tenants()->retention(),
    'rolesPermissions()->getMyScopes()' => fn (Client $sendseven): mixed => $sendseven->rolesPermissions()->getMyScopes(),
    'apiTokens()->availableScopes()' => fn (Client $sendseven): mixed => $sendseven->apiTokens()->availableScopes(),
    'users()->getTenants()' => fn (Client $sendseven): mixed => $sendseven->users()->getTenants(),
    'channels()->list()' => fn (Client $sendseven): mixed => $sendseven->channels()->list(),
    'contacts()->list()' => fn (Client $sendseven): mixed => $sendseven->contacts()->list(),
    'whatsAppTemplates()->list()' => fn (Client $sendseven): mixed => $sendseven->whatsAppTemplates()->list(),
    'webhooks()->listEndpoints()' => fn (Client $sendseven): mixed => $sendseven->webhooks()->listEndpoints(),
    'webhooks()->getAvailableEvents()' => fn (Client $sendseven): mixed => $sendseven->webhooks()->getAvailableEvents(),
    'sms()->pricelist()' => fn (Client $sendseven): mixed => $sendseven->sms()->pricelist(),
])->group('live');

it('works out what the token can do', function (): void {
    $capabilities = liveClient()->capabilities();

    expect($capabilities)->toBeInstanceOf(Capabilities::class)
        ->and($capabilities->tenantId())->not->toBe('')
        ->and($capabilities->package())->not->toBe('');

    if (! $capabilities->canCreateTenants()) {
        expect($capabilities->whyNotCreateTenants())->toBeString();
    }
})->group('live');
