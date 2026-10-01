<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Http;

use InvalidArgumentException;

/**
 * A file to upload in a multipart request.
 *
 *     FilePart::fromPath('/tmp/invoice.pdf')
 *     FilePart::fromContents($pdfBytes, 'invoice.pdf', 'application/pdf')
 */
final readonly class FilePart
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $contentType = 'application/octet-stream',
    ) {}

    public static function fromPath(string $path, ?string $filename = null, ?string $contentType = null): self
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new InvalidArgumentException("Cannot read the file at {$path}.");
        }

        $detected = function_exists('mime_content_type') ? mime_content_type($path) : false;

        return new self($contents, $filename ?? basename($path), $contentType ?? ($detected === false ? 'application/octet-stream' : $detected));
    }

    public static function fromContents(string $contents, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self($contents, $filename, $contentType);
    }
}
