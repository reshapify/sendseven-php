<?php

declare(strict_types=1);

use Reshapify\SendSeven\Data\InteractiveMessage;
use Reshapify\SendSeven\Data\Message;
use Reshapify\SendSeven\Enums\MessageType;
use Reshapify\SendSeven\Exceptions\MessageFailed;
use Reshapify\SendSeven\Exceptions\NotBillingAccountOwner;
use Reshapify\SendSeven\Exceptions\ValidationFailed;
use Reshapify\SendSeven\Http\Request;
use Reshapify\SendSeven\Http\Response;
use Reshapify\SendSeven\SendSeven;

it('sends a message and returns it typed', function (): void {
    $fake = SendSeven::fake(['POST /messages' => sendSevenFixture('webhooks/live-contract.json', 'message_create_response')]);

    $message = $fake->client->messages()->send(to: '+4915112345678', channelId: 'ch_1', text: 'Your order shipped', idempotencyKey: 'order-42-shipped');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->id)->toBe('outbound-message-fixture')
        ->and($message->text)->toBe('Fixture response');

    $fake->assertSent('POST /messages', fn (Request $request): bool => $request->body === ['to' => '+4915112345678', 'channel_id' => 'ch_1', 'text' => 'Your order shipped']
        && $request->header('Idempotency-Key') === 'order-42-shipped');
});

it('sends enum values as their wire value', function (): void {
    $fake = SendSeven::fake(['POST /messages' => sendSevenFixture('webhooks/live-contract.json', 'message_create_response')]);

    $fake->client->messages()->send(to: '+4915112345678', channelId: 'ch_1', messageType: MessageType::Text, text: 'Hi');

    $fake->assertSent('POST /messages', fn (Request $request): bool => $request->body['message_type'] === 'text');
});

it('marks a WhatsApp message as read through the platform proxy', function (): void {
    $fake = SendSeven::fake(['POST /messages/proxy' => ['success' => true]]);

    $fake->client->messages()->markWhatsAppMessageAsRead('ch_1', 'wamid.abc');

    $fake->assertSent('POST /messages/proxy', fn (Request $request): bool => $request->body === [
        'channel_id' => 'ch_1', 'endpoint' => 'messages', 'payload' => ['messaging_product' => 'whatsapp', 'status' => 'read', 'message_id' => 'wamid.abc'],
    ]);
});

it('throws when SendSeven accepts a text but reports it failed', function (): void {
    $fake = SendSeven::fake(['POST /messages' => ['id' => 'msg_9', 'status' => 'failed', 'error_message' => 'Outside the 24-hour window', 'meta' => ['error_code' => '131047']]]);

    expect(fn (): Message => $fake->client->messages()->sendText('ch_1', 'Hi', contactId: 'c_1'))
        ->toThrow(fn (MessageFailed $failed): bool => $failed->messageId === 'msg_9' && $failed->reason === 'Outside the 24-hour window' && $failed->errorCode === '131047');
});

it('trims buttons to WhatsApp limits', function (): void {
    $fake = SendSeven::fake(['POST /messages/send/interactive' => ['id' => 'i_1', 'conversation_id' => 'cv', 'status' => 'sent', 'platform' => 'whatsapp', 'type' => 'buttons']]);

    $sent = $fake->client->messages()->sendButtons('ch_1', 'c_1', str_repeat('b', 1100), ['yes' => 'Yes, everything is in stock', 'no' => 'No', 'na' => 'N/A', 'skip' => 'Skip'], footer: str_repeat('f', 70));

    expect($sent)->toBeInstanceOf(InteractiveMessage::class);
    $fake->assertSent('POST /messages/send/interactive', fn (Request $request): bool => mb_strlen($request->body['body']) === 1024
        && mb_strlen($request->body['footer']) === 60
        && $request->body['buttons'] === [['id' => 'yes', 'title' => 'Yes, everything is i'], ['id' => 'no', 'title' => 'No'], ['id' => 'na', 'title' => 'N/A']]);
});

it('falls back to a numbered text when interactive messages are refused or fail', function (Response $refusal): void {
    $fake = SendSeven::fake([
        'POST /messages/send/interactive' => $refusal,
        'POST /messages' => ['id' => 'msg_1', 'status' => 'sent'],
    ]);

    $sent = $fake->client->messages()->sendList('ch_1', 'c_1', 'Pick a store', 'Stores', [['id' => 's1', 'title' => 'Kingston'], ['id' => 's2', 'title' => 'Montego Bay', 'description' => 'North coast']]);

    expect($sent)->toBeInstanceOf(Message::class);
    $fake->assertSent('POST /messages', fn (Request $request): bool => $request->body === [
        'channel_id' => 'ch_1', 'contact_id' => 'c_1', 'message_type' => 'text', 'text' => "Pick a store\n\n1. Kingston\n2. Montego Bay\n\n_Reply with a number._",
    ]);
})->with([
    'refused' => [Response::json(['detail' => 'Interactive messages are not supported on this channel'], 422)],
    'failed' => [Response::json(['id' => 'i_1', 'conversation_id' => 'cv', 'status' => 'failed', 'platform' => 'whatsapp', 'type' => 'list', 'error' => 'Unsupported'])],
]);

it('rethrows instead of falling back when asked to', function (): void {
    $fake = SendSeven::fake(['POST /messages/send/interactive' => Response::json(['detail' => 'Nope'], 422)]);

    expect(fn (): InteractiveMessage|Message => $fake->client->messages()->sendButtons('ch_1', 'c_1', 'Ready?', ['go' => 'Go'], fallbackToText: false))->toThrow(ValidationFailed::class);
    $fake->assertNotSent('POST /messages');
});

it('finds a contact by an exact phone match, ignoring formatting and near misses', function (): void {
    $contact = fn (string $id, string $phone): array => ['id' => $id, 'phone' => $phone, 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z'];
    $fake = SendSeven::fake(['GET /contacts' => ['items' => [$contact('near', '+1 876 555 01234'), $contact('exact', '+1 (876) 555-0123')], 'pagination' => ['total' => 2, 'page' => 1, 'page_size' => 20, 'total_pages' => 1, 'has_next' => false, 'has_prev' => false]]]);

    expect($fake->client->contacts()->findOrCreateByPhone('+18765550123')->id)->toBe('exact');
    $fake->assertNotSent('POST /contacts');
});

it('creates the contact when no number matches', function (): void {
    $fake = SendSeven::fake([
        'GET /contacts' => ['items' => [], 'pagination' => ['total' => 0, 'page' => 1, 'page_size' => 20, 'total_pages' => 0, 'has_next' => false, 'has_prev' => false]],
        'POST /contacts' => ['id' => 'new', 'phone' => '+18765550123', 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z'],
    ]);

    expect($fake->client->contacts()->findOrCreateByPhone('+18765550123', 'Rich')->id)->toBe('new');
    $fake->assertSent('POST /contacts', fn (Request $request): bool => $request->body === ['name' => 'Rich', 'phone' => '+18765550123']);
});

it('walks a paginated list page by page', function (): void {
    $contact = fn (string $id): array => ['id' => $id, 'tenant_id' => 't', 'name' => $id, 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z'];
    $page = fn (int $number, array $items, bool $hasNext): array => ['items' => $items, 'pagination' => ['total' => 3, 'page' => $number, 'page_size' => 2, 'total_pages' => 2, 'has_next' => $hasNext, 'has_prev' => $number > 1]];
    $fake = SendSeven::fake(['GET /contacts' => [Response::json($page(1, [$contact('c1'), $contact('c2')], true)), Response::json($page(2, [$contact('c3')], false))]]);

    $ids = array_map(fn (Reshapify\SendSeven\Data\Contact $contact): string => $contact->id, iterator_to_array($fake->client->contacts()->list(pageSize: 2, search: 'acme')->lazy(), false));

    expect($ids)->toBe(['c1', 'c2', 'c3']);
    $fake->assertSent('GET /contacts', fn (Request $request): bool => $request->query['page'] === 2 && $request->query['search'] === 'acme');
});

it('tells you why tenant creation was refused', function (): void {
    $fake = SendSeven::fake(['POST /tenants' => new Response(403, '{"detail":"Only billing account owners can create new tenants"}')]);

    expect(fn (): Reshapify\SendSeven\Data\Tenant => $fake->client->tenants()->create(name: 'Acme'))->toThrow(NotBillingAccountOwner::class);
});
