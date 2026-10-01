<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use DateTimeImmutable;
use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Enums\ChannelType;
use Reshapify\SendSeven\Support\Attributes;

/**
 * A connected messaging integration, as sent in channel.* events. Never
 * contains credentials.
 */
final readonly class Channel extends Data
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $tenantId,
        public ChannelType|string $platform,
        public ?string $name,
        /** Phone number, @botname, page ID or email address, depending on platform. */
        public ?string $identifier,
        public bool $isActive,
        public bool $isVerified,
        public bool $isArchived,
        /** e.g. token_invalidated, partner_removed, account_offboarded */
        public ?string $disconnectionReason,
        public ?DateTimeImmutable $disconnectedAt,
        public ?DateTimeImmutable $reconnectedAt,
        /** The connect link that created this channel, when it came through one. */
        public ?string $createdViaConnectTokenId,
        public ?DateTimeImmutable $createdAt,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'channel'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->string('id'),
            tenantId: $attributes->nullableString('tenant_id'),
            platform: $attributes->enum('platform', ChannelType::class),
            name: $attributes->nullableString('name'),
            identifier: $attributes->nullableString('identifier'),
            isActive: $attributes->bool('is_active', default: false),
            isVerified: $attributes->bool('is_verified', default: false),
            isArchived: $attributes->bool('is_archived', default: false),
            disconnectionReason: $attributes->nullableString('disconnection_reason'),
            disconnectedAt: $attributes->nullableDateTime('disconnected_at'),
            reconnectedAt: $attributes->nullableDateTime('reconnected_at'),
            createdViaConnectTokenId: $attributes->nullableString('created_via_connect_token_id'),
            createdAt: $attributes->nullableDateTime('created_at'),
            raw: $data,
        );
    }

    /**
     * Connected: active and not archived.
     */
    public function isConnected(): bool
    {
        return $this->isActive && ! $this->isArchived;
    }
}
