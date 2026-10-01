<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks\Data;

use Reshapify\SendSeven\Data\Data;
use Reshapify\SendSeven\Support\Attributes;

/**
 * A file on a message: image, video, audio, document or sticker.
 */
final readonly class Attachment extends Data
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public ?string $id,
        public string $type,
        public ?string $filename,
        public ?string $contentType,
        public ?int $fileSize,
        /** SendSeven's download endpoint: needs the API token, never expires. */
        public ?string $url,
        /** A pre-signed URL: no token needed, expires 24 hours after the event. */
        public ?string $signedUrl,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, string $path = 'attachment'): self
    {
        $attributes = new Attributes($data, $path);

        return new self(
            id: $attributes->nullableString('id'),
            type: $attributes->nullableString('type') ?? 'file',
            filename: $attributes->nullableString('filename'),
            contentType: $attributes->nullableString('content_type'),
            fileSize: $attributes->nullableInt('file_size'),
            url: $attributes->nullableString('url'),
            signedUrl: $attributes->nullableString('signed_url'),
            raw: $data,
        );
    }

    /**
     * The URL to fetch the file from without a token, when there is one.
     */
    public function downloadUrl(): ?string
    {
        return $this->signedUrl ?? $this->url;
    }

    /**
     * SendSeven couldn't download the media itself and passed the platform's
     * raw data through ("source": "platform").
     */
    public function isUnprocessed(): bool
    {
        return ($this->raw()['source'] ?? null) === 'platform';
    }
}
