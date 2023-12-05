<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Iterator;

use Countable;
use Generator;
use ReturnTypeWillChange;
use RuntimeException;
use Traversable;
use function count;
use function current;
use function key;
use function next;
use function reset;

/**
 * Iterator for wrapping a Traversable and caching its results.
 *
 * @internal
 */
final class CachingIterator implements Countable, Iterator
{
    private array $items = [];

    private ?Generator $iterator;

    private bool $iteratorAdvanced = false;

    private bool $iteratorExhausted = false;

    /**
     * Initialize the iterator and stores the first item in the cache. This
     * effectively rewinds the Traversable and the wrapping Generator, which
     * will execute up to its first yield statement. Additionally, this mimics
     * behavior of the SPL iterators and allows users to omit an explicit call
     * to rewind() before using the other methods.
     */
    public function __construct(Traversable $iterator)
    {
        $this->iterator = $this->wrapTraversable($iterator);
        $this->storeCurrentItem();
    }

    public function __destruct()
    {
        $this->iterator = null;
    }

    /** @see https://php.net/countable.count */
    public function count(): int
    {
        $this->exhaustIterator();

        return count($this->items);
    }

    #[ReturnTypeWillChange]
    public function current(): bool
    {
        return current($this->items);
    }

    #[ReturnTypeWillChange]
    public function key(): string|int|null
    {
        return key($this->items);
    }

    /** @see http://php.net/iterator.next */
    public function next(): void
    {
        if (!$this->iteratorExhausted) {
            $this->getIterator()->next();
            $this->storeCurrentItem();
        }

        next($this->items);
    }

    /** @see http://php.net/iterator.rewind */
    public function rewind(): void
    {
        /* If the iterator has advanced, exhaust it now so that future iteration
         * can rely on the cache.
         */
        if ($this->iteratorAdvanced) {
            $this->exhaustIterator();
        }

        reset($this->items);
    }

    public function toArray(): array
    {
        $this->exhaustIterator();

        return $this->items;
    }

    /** @see http://php.net/iterator.valid */
    public function valid(): bool
    {
        return $this->key() !== null;
    }

    /**
     * Ensures that the inner iterator is fully consumed and cached.
     */
    private function exhaustIterator(): void
    {
        while (!$this->iteratorExhausted) {
            $this->next();
        }

        $this->iterator = null;
    }

    private function getIterator(): Generator
    {
        if ($this->iterator === null) {
            throw new RuntimeException('Iterator has already been destroyed');
        }

        return $this->iterator;
    }

    /**
     * Stores the current item in the cache.
     */
    private function storeCurrentItem(): void
    {
        $key = $this->getIterator()->key();

        if ($key === null) {
            return;
        }

        $this->items[$key] = $this->getIterator()->current();
    }

    private function wrapTraversable(Traversable $traversable): Generator
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;

            $this->iteratorAdvanced = true;
        }

        $this->iteratorExhausted = true;
    }
}
