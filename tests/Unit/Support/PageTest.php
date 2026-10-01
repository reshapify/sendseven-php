<?php

declare(strict_types=1);

use Reshapify\SendSeven\Pagination\Page;
use Reshapify\SendSeven\Pagination\Pagination;

function pageOf(int $page, int $totalPages, array $items, ?Closure $fetch = null): Page
{
    return new Page($items, new Pagination(total: 5, page: $page, pageSize: 2, totalPages: $totalPages, hasNext: $page < $totalPages, hasPrevious: $page > 1), $fetch);
}

it('walks every page lazily, fetching each only when reached', function () {
    $fetched = [];
    $fetch = function (int $page) use (&$fetch, &$fetched): Page {
        $fetched[] = $page;

        return pageOf($page, 3, $page === 2 ? ['c', 'd'] : ['e'], $fetch);
    };

    $first = pageOf(1, 3, ['a', 'b'], $fetch);

    expect(iterator_to_array($first, false))->toBe(['a', 'b'])
        ->and($fetched)->toBe([])
        ->and(iterator_to_array($first->lazy(), false))->toBe(['a', 'b', 'c', 'd', 'e'])
        ->and($fetched)->toBe([2, 3]);
});

it('has no next page on the last page', function () {
    expect(pageOf(2, 2, ['x'], fn () => throw new LogicException('should not fetch'))->nextPage())->toBeNull();
});
