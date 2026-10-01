<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Support;

use Closure;
use Reshapify\SendSeven\Exceptions\UnexpectedResponse;
use Reshapify\SendSeven\Pagination\Page;
use Reshapify\SendSeven\Pagination\Pagination;

/**
 * Builds typed results from decoded responses.
 *
 * @internal used by the generated resources
 */
final class Hydrate
{
    /**
     * @template T
     *
     * @param  array<array-key, mixed>  $items
     * @param  Closure(array<array-key, mixed>, string): T  $make
     * @return list<T>
     */
    public static function list(array $items, Closure $make, string $path = 'response'): array
    {
        $list = [];

        foreach (array_values($items) as $index => $item) {
            $list[] = is_array($item) ? $make($item, "{$path}[{$index}]") : throw UnexpectedResponse::because("{$path}[{$index}] should be an object, got ".get_debug_type($item));
        }

        return $list;
    }

    /**
     * A page of a list response: {"items": [...], "pagination": {...}}.
     *
     * @template T
     *
     * @param  array<array-key, mixed>  $data
     * @param  Closure(array<array-key, mixed>, string): T  $make
     * @param  (Closure(int): Page<T>)|null  $fetchPage
     * @return Page<T>
     */
    public static function page(array $data, Closure $make, ?Closure $fetchPage = null): Page
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : throw UnexpectedResponse::because('response.items is missing');
        $pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : throw UnexpectedResponse::because('response.pagination is missing');

        return new Page(self::list($items, $make, 'response.items'), Pagination::fromArray($pagination), $fetchPage);
    }
}
