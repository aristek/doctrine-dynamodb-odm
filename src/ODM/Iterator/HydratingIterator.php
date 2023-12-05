<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Iterator;

use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Generator;
use Iterator;
use ReflectionException;
use ReturnTypeWillChange;
use RuntimeException;
use Traversable;

/**
 * Iterator that wraps a traversable and hydrates results into objects
 *
 * @internal
 */
final class HydratingIterator implements Iterator
{
    private ?Generator $iterator;

    public function __construct(
        Traversable $traversable,
        private readonly UnitOfWork $unitOfWork,
        private readonly ClassMetadata $class,
        private array $unitOfWorkHints = [],
    ) {
        $this->iterator = $this->wrapTraversable($traversable);
    }

    public function __destruct()
    {
        $this->iterator = null;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws ReflectionException
     */
    #[ReturnTypeWillChange]
    public function current(): ?object
    {
        return $this->hydrate($this->getIterator()->current());
    }

    #[ReturnTypeWillChange]
    public function key(): mixed
    {
        return $this->getIterator()->key();
    }

    /** @see http://php.net/iterator.next */
    public function next(): void
    {
        $this->getIterator()->next();
    }

    /** @see http://php.net/iterator.rewind */
    public function rewind(): void
    {
        $this->getIterator()->rewind();
    }

    /** @see http://php.net/iterator.valid */
    public function valid(): bool
    {
        return $this->key() !== null;
    }

    private function getIterator(): Generator
    {
        if ($this->iterator === null) {
            throw new RuntimeException('Iterator has already been destroyed');
        }

        return $this->iterator;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws ReflectionException
     */
    private function hydrate(?array $document): ?object
    {
        return $document !== null ? $this->unitOfWork->getOrCreateDocument(
            $this->class->name,
            $document,
            $this->unitOfWorkHints
        ) : null;
    }

    private function wrapTraversable(Traversable $traversable): Generator
    {
        foreach ($traversable as $key => $value) {
            yield $key => $value;
        }
    }
}
