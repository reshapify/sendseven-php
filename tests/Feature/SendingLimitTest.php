<?php

declare(strict_types=1);

use Reshapify\SendSeven\SendSeven;

it("reads a WhatsApp number's quality rating and messaging tier", function (): void {
    $fake = SendSeven::fake(['GET /whatsapp/channels/ch_wa/sending-limit' => [
        'channel_id' => 'ch_wa', 'channel_type' => 'whatsapp', 'messaging_limit_tier' => 'TIER_250', 'quality_rating' => 'GREEN',
        'throughput_level' => 'STANDARD', 'phone_status' => 'CONNECTED', 'refreshed_at' => '2026-10-28T02:01:00+00:00',
        'last_checked_at' => '2026-10-28T02:01:00+00:00', 'stale' => false, 'supported' => true, 'error' => null,
    ]]);

    $limit = $fake->client->whatsApp()->getSendingLimit('ch_wa');

    expect($limit->qualityRating)->toBe('GREEN')
        ->and($limit->messagingLimitTier)->toBe('TIER_250')
        ->and($limit->phoneStatus)->toBe('CONNECTED')
        ->and($limit->stale)->toBeFalse();
});
