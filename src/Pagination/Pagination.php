<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Pagination;

use Reshapify\SendSeven\Support\Attributes;

/**
 * Where a page sits in the full result: SendSeven's "pagination" object.
 */
final readonly class Pagination
{
    public function __construct(
        public int $total,
        public int $page,
        public int $pageSize,
        public int $totalPages,
        public bool $hasNext,
        public bool $hasPrevious,
        public ?string $nextCursor = null,
        public ?string $previousCursor = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $attributes = new Attributes($data, 'pagination');

        return new self(
            total: $attributes->int('total'),
            page: $attributes->int('page'),
            pageSize: $attributes->int('page_size'),
            totalPages: $attributes->int('total_pages'),
            hasNext: $attributes->bool('has_next'),
            hasPrevious: $attributes->bool('has_prev'),
            nextCursor: $attributes->nullableString('next_cursor'),
            previousCursor: $attributes->nullableString('prev_cursor'),
        );
    }
}
