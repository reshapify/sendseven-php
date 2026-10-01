<?php

declare(strict_types=1);

use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Onboarding\ConnectLink;
use Reshapify\SendSeven\Onboarding\InstagramMode;
use Reshapify\SendSeven\Onboarding\WhatsAppMode;
use Reshapify\SendSeven\SendSeven;

function connectTokenResponse(array $overrides = []): array
{
    return [
        'id' => 'cct_9a8b7c6d', 'name' => 'Acme', 'token_prefix' => 's7_cc_a1', 'allowed_channel_types' => ['whatsapp'],
        'allowed_channel_modes' => ['whatsapp' => ['classic']], 'partner_name' => 'Renotify', 'partner_redirect_url' => 'https://app.test/done',
        'connect_url' => 'https://app.sendseven.com/connect/s7_cc_a1b2', 'max_uses' => 1, 'current_uses' => 0, 'use_window_minutes' => 30,
        'first_used_at' => null, 'expires_at' => '2026-10-03T09:00:00Z', 'is_revoked' => false, 'is_locked' => false, 'is_valid' => true,
        'remaining_uses' => 1, 'created_at' => '2026-10-01T09:00:00Z', 'revoked_at' => null, 'created_by_user_id' => null,
        'token' => 's7_cc_a1b2', 'warning' => null, ...$overrides,
    ];
}

it('creates a branded, single-use WhatsApp link without Coexistence', function (): void {
    $fake = SendSeven::fake(['POST /channel-connect-tokens' => connectTokenResponse()]);

    $link = $fake->client->connectLinks()->create(
        ConnectLink::for(ChannelType::WhatsApp, ChannelType::Instagram)
            ->modes(whatsapp: [WhatsAppMode::Classic], instagram: [InstagramMode::Messaging])
            ->brandedAs('Renotify')
            ->redirectTo('https://app.test/done')
            ->named('Acme')
            ->singleUse()
            ->expiresIn(hours: 48),
    );

    expect($link->connectUrl)->toBe('https://app.sendseven.com/connect/s7_cc_a1b2')
        ->and($link->id)->toBe('cct_9a8b7c6d');

    $fake->assertSent('POST /channel-connect-tokens', fn (Request $request): bool => $request->body === [
        'allowed_channel_types' => ['whatsapp', 'instagram'],
        'name' => 'Acme',
        'expires_in_hours' => 48,
        'max_uses' => 1,
        'allowed_channel_modes' => ['whatsapp' => ['classic'], 'instagram' => ['messaging']],
        'partner_name' => 'Renotify',
        'partner_redirect_url' => 'https://app.test/done',
    ]);
});

it("creates the link in a customer's sub-account", function (): void {
    $fake = SendSeven::fake(['POST /channel-connect-tokens' => connectTokenResponse()]);

    $fake->client->forTenant('tenant_acme')->connectLinks()->create(ConnectLink::for('telegram'));

    $fake->assertSent('POST /channel-connect-tokens', fn (Request $request): bool => $request->header('X-Tenant-ID') === 'tenant_acme');
});

it('refuses channels the connect page cannot connect, and impossible settings', function (Closure $build, string $message): void {
    expect($build)->toThrow(InvalidArgumentException::class, $message);
})->with([
    'RCS' => [fn (): ConnectLink => ConnectLink::for(ChannelType::Rcs), 'RCS is set up by SendSeven'],
    'browser push' => [fn (): ConnectLink => ConnectLink::for(ChannelType::BrowserPush), "can't connect browser_push"],
    'no channels' => [fn (): ConnectLink => ConnectLink::for(), 'at least one channel type'],
    'empty modes' => [fn (): ConnectLink => ConnectLink::for(ChannelType::WhatsApp)->modes(whatsapp: []), 'at least one whatsapp mode'],
    'too long' => [fn (): ConnectLink => ConnectLink::for(ChannelType::WhatsApp)->expiresIn(hours: 200), '1 to 168 hours'],
]);
