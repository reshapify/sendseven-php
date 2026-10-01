<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Pagination;

use ArrayIterator;
use Closure;
use Countable;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * One page of a list. Iterate it for this page's items, or call lazy() to
 * walk every page in turn, fetching each only when it is reached.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     * @param  (Closure(int $page): self<TItem>)|null  $fetchPage  fetches another page of the same list
     */
    public function __construct(
        public array $items,
        public Pagination $pagination,
        private ?Closure $fetchPage = null,
    ) {}

    public function hasNextPage(): bool
    {
        return $this->pagination->hasNext && $this->fetchPage instanceof Closure;
    }

    /**
     * @return self<TItem>|null
     */
    public function nextPage(): ?self
    {
        return $this->hasNextPage() && $this->fetchPage instanceof Closure
            ? ($this->fetchPage)($this->pagination->page + 1)
            : null;
    }

    /**
     * Every item from this page onwards, across all pages.
     *
     * @return Generator<int, TItem>
     */
    public function lazy(): Generator
    {
        $page = $this;

        while ($page instanceof self) {
            yield from $page->items;

            $page = $page->nextPage();
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
