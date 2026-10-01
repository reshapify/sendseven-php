<?php

declare(strict_types=1);

use Reshapify\SendSeven\Data\Message;
use Reshapify\SendSeven\Enums\MessageType;
use Reshapify\SendSeven\Exceptions\NotBillingAccountOwner;
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
