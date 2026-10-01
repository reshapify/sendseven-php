<?php

declare(strict_types=1);

use Reshapify\SendSeven\SendSeven;
use Reshapify\SendSeven\Testing\Fake;

// Shapes captured from the live API on 1 Oct 2026, with IDs replaced.

function tenantPayload(string $id, array $overrides = []): array
{
    return [
        'id' => $id, 'name' => 'Shift Workspace', 'slug' => 'shift', 'billing_account_id' => 'ba_1', 'pricing_model_id' => null,
        'is_trial' => false, 'trial_ends_at' => null, 'is_active' => true, 'subscription_tier' => 'professional', 'multi_agent_mode' => true,
        'auto_summarize_on_close' => 'ask', 'auto_summarize_live_chat' => false, 'allow_messaging_other_agents_conversations' => true,
        'sms_enabled' => false, 'rcs_enabled' => false, 'created_at' => '2026-09-09T20:45:11', ...$overrides,
    ];
}

function featuresPayload(bool $multiTenant, string $package = 'API_ONLY'): array
{
    return [
        'package_type' => $package, 'is_api_only' => true, 'ai_enabled' => true, 'is_trial' => false, 'subscription_status' => 'active',
        'ui_features' => ['multi_tenant' => $multiTenant, 'browser_push' => true],
        'api_features' => ['api_messages' => true],
        'disabled_features' => $multiTenant ? ['conversations'] : ['conversations', 'multi_tenant'],
        'pricing_model' => ['name' => 'API Only', 'cost_message_millicents' => 400],
    ];
}

function capabilitiesFake(bool $multiTenant, array $scopes, string $package = 'API_ONLY'): Fake
{
    return SendSeven::fake([
        'GET /permissions/me/scopes' => ['tenant_id' => 'tenant_shift', 'scopes' => $scopes, 'total' => count($scopes)],
        'GET /tenants/me' => [tenantPayload('tenant_other', ['name' => 'Other']), tenantPayload('tenant_shift', ['sms_enabled' => true])],
        'GET /tenants/features' => featuresPayload($multiTenant, $package),
    ]);
}

it('describes a partner-capable token', function (): void {
    $capabilities = capabilitiesFake(multiTenant: true, scopes: ['*:*'])->client->capabilities();

    expect($capabilities->tenantId())->toBe('tenant_shift')
        ->and($capabilities->tenant?->name)->toBe('Shift Workspace')
        ->and($capabilities->package())->toBe('API_ONLY')
        ->and($capabilities->hasMultiTenant())->toBeTrue()
        ->and($capabilities->canCreateTenants())->toBeTrue()
        ->and($capabilities->canManageTenants())->toBeTrue()
        ->and($capabilities->sendsSms())->toBeTrue()
        ->and($capabilities->sendsRcs())->toBeFalse()
        ->and($capabilities->whyNotCreateTenants())->toBeNull();
});

it('explains a plan without multi-tenant management', function (): void {
    $capabilities = capabilitiesFake(multiTenant: false, scopes: ['*:*'], package: 'BASIC')->client->capabilities();

    expect($capabilities->canCreateTenants())->toBeFalse()
        ->and($capabilities->whyNotCreateTenants())->toContain("plan (BASIC) doesn't include multi-tenant management");
});

it('honours scope wildcards', function (): void {
    $capabilities = capabilitiesFake(multiTenant: true, scopes: ['contacts:*', 'messages:create'])->client->capabilities();

    expect($capabilities->hasScope('contacts:read'))->toBeTrue()
        ->and($capabilities->hasScope('messages:create'))->toBeTrue()
        ->and($capabilities->hasScope('messages:read'))->toBeFalse()
        ->and($capabilities->canCreateTenants())->toBeFalse()
        ->and($capabilities->whyNotCreateTenants())->toBe('The token lacks the tenants:create scope.');
});
