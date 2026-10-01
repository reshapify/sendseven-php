<?php

declare(strict_types=1);

use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Enums\ContactMethodType;
use Reshapify\SendSeven\Enums\MessageDirection;
use Reshapify\SendSeven\Webhooks\Events\ChannelEvent;
use Reshapify\SendSeven\Webhooks\Events\MessageReactionChanged;
use Reshapify\SendSeven\Webhooks\Events\MessageReceived;
use Reshapify\SendSeven\Webhooks\Events\MessageStatusUpdated;
use Reshapify\SendSeven\Webhooks\Events\UnknownEvent;
use Reshapify\SendSeven\Webhooks\EventType;
use Reshapify\SendSeven\Webhooks\InvalidSignature;
use Reshapify\SendSeven\Webhooks\Webhook;
use Reshapify\SendSeven\Webhooks\WebhookVerifier;

const SECRET = '9f8e7d6c5b4a39281706f5e4d3c2b1a09f8e7d6c5b4a39281706f5e4d3c2b1a0';

describe('the verification challenge', function (): void {
    $body = '{"type":"sendseven_verification","challenge":"a1b2c3d4e5f6","webhook_id":"wh_abc123","timestamp":"2026-02-10T18:00:00Z"}';

    it('is recognised by its header or its body', function () use ($body): void {
        expect(Webhook::isVerificationChallenge($body, ['X-SendSeven-Event' => 'verification']))->toBeTrue()
            ->and(Webhook::isVerificationChallenge($body))->toBeTrue()
            ->and(Webhook::isVerificationChallenge('{"type":"message.received"}', ['X-SendSeven-Event' => 'message.received']))->toBeFalse()
            ->and(Webhook::isVerificationChallenge('not json'))->toBeFalse();
    });

    it('is answered by echoing the challenge', function () use ($body): void {
        expect(Webhook::challengeResponse($body))->toBe(['challenge' => 'a1b2c3d4e5f6']);
    });
});

describe('signatures', function (): void {
    $body = '{"id":"evt_1","type":"message.read"}';

    it('accepts a delivery signed over the timestamp and body', function () use ($body): void {
        $verifier = new WebhookVerifier(SECRET);

        expect($verifier->isValid($body, Webhook::sign($body, SECRET, 1_770_000_000), now: 1_770_000_010))->toBeTrue();
    });

    it('matches the signature documented by SendSeven', function (): void {
        // HMAC-SHA256(secret, "{timestamp}.{body}"), lowercase hex, "sha256=" prefix.
        expect(WebhookVerifier::signature('secret', '1770000000', '{}'))
            ->toBe('sha256='.hash_hmac('sha256', '1770000000.{}', 'secret'));
    });

    it('rejects the old body-only signature, a stale timestamp, a wrong secret and missing headers', function (array $headers, string $message) use ($body): void {
        expect(fn () => (new WebhookVerifier(SECRET))->verify($body, $headers, now: 1_770_000_010))->toThrow(InvalidSignature::class, $message);
    })->with([
        'body-only HMAC' => [['X-SendSeven-Timestamp' => '1770000000', 'X-SendSeven-Signature' => 'sha256='.hash_hmac('sha256', '{"id":"evt_1","type":"message.read"}', SECRET)], 'does not match'],
        'stale' => [Webhook::sign('{"id":"evt_1","type":"message.read"}', SECRET, 1_769_999_000), 'beyond the 300-second tolerance'],
        'wrong secret' => [Webhook::sign('{"id":"evt_1","type":"message.read"}', 'other', 1_770_000_000), 'does not match'],
        'unsigned' => [[], 'no X-SendSeven-Signature'],
    ]);

    it('reads headers however the framework passes them', function () use ($body): void {
        $signed = Webhook::sign($body, SECRET, 1_770_000_000);
        $server = ['HTTP_X_SENDSEVEN_TIMESTAMP' => $signed['X-SendSeven-Timestamp'], 'HTTP_X_SENDSEVEN_SIGNATURE' => [$signed['X-SendSeven-Signature']]];

        expect((new WebhookVerifier(SECRET))->isValid($body, $server, now: 1_770_000_000))->toBeTrue();
    });

    it("can also require the endpoint's static Authorization header", function () use ($body): void {
        $verifier = new WebhookVerifier(SECRET, authorization: 'Bearer hook-secret');
        $signed = Webhook::sign($body, SECRET, 1_770_000_000);

        expect($verifier->isValid($body, [...$signed, 'Authorization' => 'Bearer hook-secret'], now: 1_770_000_000))->toBeTrue()
            ->and(fn () => $verifier->verify($body, $signed, now: 1_770_000_000))->toThrow(InvalidSignature::class, 'Authorization');
    });
});

describe('events from a live delivery', function (): void {
    it('parses an inbound text message', function (): void {
        $body = json_encode(sendSevenFixture('webhooks/live-contract.json', 'message_received_text'), JSON_THROW_ON_ERROR);
        $event = Webhook::constructEvent($body, Webhook::sign($body, SECRET), SECRET);

        expect($event)->toBeInstanceOf(MessageReceived::class)
            ->and($event->type)->toBe(EventType::MessageReceived)
            ->and($event->message->text)->toBe('summer')
            ->and($event->message->fromId)->toBe('15555550100')
            ->and($event->message->platform)->toBe(ChannelType::WhatsApp)
            ->and($event->message->direction)->toBe(MessageDirection::Inbound)
            ->and($event->message->externalId)->toBe('wamid.fixture.text')
            ->and($event->message->contactInfo()['whatsapp_bsuid'])->toBe('JM.fixture-participant')
            ->and($event->contact?->phone)->toBe('+15555550100')
            ->and($event->contact?->method(ContactMethodType::WhatsAppBsuid)?->channelId)->toBe('ch_test123')
            ->and($event->contactMethod?->type)->toBe(ContactMethodType::WhatsAppId)
            ->and($event->conversation?->lastCustomerMessageAt?->format('H:i:s'))->toBe('05:25:47');
    });

    it('parses an inbound image with its signed URL', function (): void {
        $event = Webhook::parse(json_encode(sendSevenFixture('webhooks/live-contract.json', 'message_received_image'), JSON_THROW_ON_ERROR));

        expect($event)->toBeInstanceOf(MessageReceived::class)
            ->and($event->message->type)->toBe('image')
            ->and($event->message->attachment()?->contentType)->toStartWith('image/')
            ->and($event->message->attachment()?->downloadUrl())->not->toBeNull();
    });

    it('parses a read receipt', function (): void {
        $event = Webhook::parse(json_encode(sendSevenFixture('webhooks/live-contract.json', 'message_status'), JSON_THROW_ON_ERROR));

        expect($event)->toBeInstanceOf(MessageStatusUpdated::class)
            ->and($event->status())->toBe('read')
            ->and($event->failed())->toBeFalse()
            ->and($event->message->readAt?->format('H:i:s'))->toBe('05:30:01');
    });
});

describe('documented events', function (): void {
    it('explains why a message failed', function (): void {
        $event = Webhook::parse('{"id":"evt_5","type":"message.failed","data":{"message":{"id":"msg_1","message_type":"text","status":"failed","meta":{"error":"Message undeliverable","error_code":"131047"}}}}');

        expect($event)->toBeInstanceOf(MessageStatusUpdated::class)
            ->and($event->failed())->toBeTrue()
            ->and($event->message->errorCode())->toBe('131047')
            ->and($event->message->errorMessage())->toBe('Message undeliverable');
    });

    it('reads a tapped button however SendSeven reports it', function (array $message, string $id, string $title, bool $fromList): void {
        $event = Webhook::parse(json_encode(['id' => 'evt', 'type' => 'message.received', 'data' => ['message' => ['id' => 'm', ...$message]]], JSON_THROW_ON_ERROR));

        expect($event)->toBeInstanceOf(MessageReceived::class);
        $reply = $event->message->buttonReply();
        expect($reply?->id)->toBe($id)->and($reply?->title)->toBe($title)->and($reply?->fromList)->toBe($fromList);
    })->with([
        'meta.button' => [['message_type' => 'interactive', 'text' => 'Yes', 'meta' => ['button' => ['id' => 'confirm', 'text' => 'Yes']]], 'confirm', 'Yes', false],
        'button_reply attachment' => [['message_type' => 'interactive', 'text' => 'No', 'attachments' => [['type' => 'button_reply', 'button_id' => 'decline', 'button_title' => 'No']]], 'decline', 'No', false],
        'list reply' => [['message_type' => 'interactive', 'text' => 'Tuesday', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'tue', 'title' => 'Tuesday']]], 'tue', 'Tuesday', true],
    ]);

    it('ties a new channel to the connect link that created it', function (): void {
        $event = Webhook::parse('{"id":"evt_channel_001","type":"channel.created","event_id":"ch_123","data":{"channel":{"id":"ch_123","platform":"whatsapp","is_active":true,"is_verified":true,"is_archived":false,"created_via_connect_token_id":"cct_9a8b7c6d"}}}');

        expect($event)->toBeInstanceOf(ChannelEvent::class)
            ->and($event->wasConnectedVia('cct_9a8b7c6d'))->toBeTrue()
            ->and($event->wasConnectedVia('cct_other'))->toBeFalse()
            ->and($event->channel->isConnected())->toBeTrue();
    });

    it('reports why a channel disconnected', function (): void {
        $event = Webhook::parse('{"id":"e","type":"channel.updated","data":{"change":"status_changed","channel":{"id":"ch_1","platform":"whatsapp","is_active":false,"is_archived":false,"disconnection_reason":"token_invalidated"}}}');

        expect($event)->toBeInstanceOf(ChannelEvent::class)
            ->and($event->change())->toBe('status_changed')
            ->and($event->channel->isConnected())->toBeFalse()
            ->and($event->channel->disconnectionReason)->toBe('token_invalidated');
    });

    it('parses reactions', function (): void {
        $event = Webhook::parse('{"id":"e","type":"message.reaction","data":{"reaction":{"emoji":"👍","action":"added","from_id":"+1234567890"},"message":{"id":"msg_def456","message_type":"text"}}}');

        expect($event)->toBeInstanceOf(MessageReactionChanged::class)
            ->and($event->reaction->emoji)->toBe('👍')
            ->and($event->reaction->added)->toBeTrue();
    });

    it('keeps events it has no class for, including ones SendSeven adds later', function (): void {
        $known = Webhook::parse('{"id":"e1","type":"link.clicked","data":{"url":"https://x.test"}}');
        $new = Webhook::parse('{"id":"e2","type":"payment.captured","data":{"amount":5}}');

        expect($known)->toBeInstanceOf(UnknownEvent::class)
            ->and($known->type)->toBe(EventType::LinkClicked)
            ->and($new->typeName())->toBe('payment.captured')
            ->and($new->data)->toBe(['amount' => 5]);
    });

    it('refuses a body that is not an event', function (): void {
        expect(fn (): Reshapify\SendSeven\Webhooks\Events\Event => Webhook::parse('{"hello":"world"}'))->toThrow(InvalidSignature::class, 'not a JSON object with an "id" and a "type"');
    });
});
