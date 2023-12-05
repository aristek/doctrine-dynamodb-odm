<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

use BadMethodCallException;
use Closure;
use Doctrine\Common\Collections\Collection as BaseCollection;
use Doctrine\Persistence\Mapping\MappingException;
use Exception;
use ReflectionException;
use ReturnTypeWillChange;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\UnitOfWork;
use Traversable;
use function array_combine;
use function array_diff_key;
use function array_map;
use function array_udiff_assoc;
use function array_values;
use function count;
use function get_class;
use function is_object;
use function method_exists;

/**
 * Trait with methods needed to implement PersistentCollectionInterface.
 */
trait PersistentCollectionTrait
{
    /**
     * The wrapped Collection instance.
     */
    private BaseCollection $coll;

    /**
     * The DocumentManager that manages the persistence of the collection.
     */
    private ?DocumentManager $dm;

    /**
     * The raw dynamo data that will be used to initialize this collection.
     */
    private array $dynamoData = [];

    /**
     * Any hints to account for during reconstitution/lookup of the documents.
     */
    private array $hints = [];

    /**
     * Whether the collection has already been initialized.
     */
    private bool $initialized = true;

    /**
     * Whether the collection is dirty and needs to be synchronized with the database
     * when the UnitOfWork that manages its persistent state commits.
     */
    private bool $isDirty = false;

    private ?array $mapping = null;

    /**
     * Collection's owning document
     */
    private ?object $owner;

    /**
     * A snapshot of the collection at the moment it was fetched from the database.
     * This is used to create a diff of the collection at commit time.
     */
    private array $snapshot = [];

    /**
     * The UnitOfWork that manages the persistence of the collection.
     */
    private UnitOfWork $uow;

    /**
     * Cleanup internal state of cloned persistent collection.
     *
     * The following problems have to be prevented:
     * 1. Added documents are added to old PersistentCollection
     * 2. New collection is not dirty, if reused on other document nothing changes.
     * 3. Snapshot leads to invalid diffs being generated.
     * 4. Lazy loading grabs documents from old owner object.
     * 5. New collection is connected to old owner and leads to duplicate keys.
     *
     * @throws PersistentCollectionException
     */
    public function __clone()
    {
        if (is_object($this->coll)) {
            $this->coll = clone $this->coll;
        }

        $this->initialize();

        $this->owner = null;
        $this->snapshot = [];

        $this->changed();
    }

    /**
     * Called by PHP when this collection is serialized. Ensures that the
     * internal state of the collection can be reproduced after serialization
     *
     * @return string[]
     */
    public function __sleep()
    {
        return ['coll', 'initialized', 'dynamoData', 'snapshot', 'isDirty', 'hints'];
    }

    /**
     * @throws PersistentCollectionException
     */
    public function add($element): bool
    {
        return $this->doAdd($element, false);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function clear(): void
    {
        if ($this->initialized && $this->isEmpty()) {
            return;
        }

        if ($this->isOrphanRemovalEnabled()) {
            $this->initialize();
            foreach ($this->coll as $element) {
                $this->uow->scheduleOrphanRemoval($element);
            }
        }

        $this->dynamoData = [];
        $this->coll->clear();

        // Nothing to do for inverse-side collections
        if (!$this->mapping['isOwningSide']) {
            return;
        }

        // Nothing to do if the collection was initialized but contained no data
        if ($this->initialized && empty($this->snapshot)) {
            return;
        }

        $this->changed();
        $this->uow->scheduleCollectionDeletion($this);
        $this->takeSnapshot();
    }

    public function clearSnapshot(): void
    {
        $this->snapshot = [];
        $this->isDirty = $this->coll->count() !== 0;
    }

    /**
     * @throws PersistentCollectionException
     */
    public function contains($element): bool
    {
        $this->initialize();

        return $this->coll->contains($element);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function containsKey($key): bool
    {
        $this->initialize();

        return $this->coll->containsKey($key);
    }

    /**
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function count(): int
    {
        // Workaround around not being able to directly count inverse collections anymore
        $this->initialize();

        return $this->coll->count();
    }

    /**
     * Gets the element of the collection at the current iterator position.
     */
    public function current()
    {
        return $this->coll->current();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function exists(Closure $p): bool
    {
        $this->initialize();

        return $this->coll->exists($p);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function filter(Closure $p): BaseCollection
    {
        $this->initialize();

        return $this->coll->filter($p);
    }

    public function findFirst(Closure $p)
    {
        if (!method_exists($this->coll, 'findFirst')) {
            throw new BadMethodCallException('findFirst() is only available since doctrine/collections v2');
        }

        return $this->coll->findFirst($p);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function first()
    {
        $this->initialize();

        return $this->coll->first();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function forAll(Closure $p): bool
    {
        $this->initialize();

        return $this->coll->forAll($p);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function get($key)
    {
        $this->initialize();

        return $this->coll->get($key);
    }

    public function getDeleteDiff(): array
    {
        return array_udiff_assoc(
            $this->snapshot,
            $this->coll->toArray(),
            static fn($a, $b) => $a === $b ? 0 : 1,
        );
    }

    public function getDeletedDocuments(): array
    {
        $coll = $this->coll->toArray();
        $loadedObjectsByOid = array_combine(array_map('spl_object_id', $this->snapshot), $this->snapshot);
        $newObjectsByOid = array_combine(array_map('spl_object_id', $coll), $coll);

        return array_values(array_diff_key($loadedObjectsByOid, $newObjectsByOid));
    }

    public function getDynamoData(): array
    {
        return $this->dynamoData;
    }

    public function setDynamoData(array $dynamoData): void
    {
        $this->dynamoData = $dynamoData;
    }

    public function getHints(): array
    {
        return $this->hints;
    }

    public function setHints(array $hints): void
    {
        $this->hints = $hints;
    }

    public function getInsertDiff(): array
    {
        return array_udiff_assoc(
            $this->coll->toArray(),
            $this->snapshot,
            static fn($a, $b) => $a === $b ? 0 : 1,
        );
    }

    public function getInsertedDocuments(): array
    {
        $coll = $this->coll->toArray();
        $newObjectsByOid = array_combine(array_map('spl_object_id', $coll), $coll);
        $loadedObjectsByOid = array_combine(array_map('spl_object_id', $this->snapshot), $this->snapshot);

        return array_values(array_diff_key($newObjectsByOid, $loadedObjectsByOid));
    }

    /**
     * @throws Exception
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function getIterator(): Traversable
    {
        $this->initialize();

        return $this->coll->getIterator();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function getKeys(): array
    {
        $this->initialize();

        return $this->coll->getKeys();
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }

    public function getOwner(): ?object
    {
        return $this->owner;
    }

    public function setOwner(object $document, array $mapping): void
    {
        $this->owner = $document;
        $this->mapping = $mapping;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    /**
     * @throws DynamoDBException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getTypeClass(): ClassMetadata
    {
        if ($this->dm === null) {
            throw new DynamoDBException(
                'No DocumentManager is associated with this PersistentCollection,
                please set one using setDocumentManager method.'
            );
        }

        if (empty($this->mapping)) {
            throw new DynamoDBException(
                'No mapping is associated with this PersistentCollection, please set one using setOwner method.'
            );
        }

        if (empty($this->mapping['targetDocument'])) {
            throw new DynamoDBException(
                'Specifying targetDocument is required for the ClassMetadata to be obtained.'
            );
        }

        return $this->dm->getClassMetadata($this->mapping['targetDocument']);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function getValues(): array
    {
        $this->initialize();

        return $this->coll->getValues();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function indexOf($element): bool
    {
        $this->initialize();

        return $this->coll->indexOf($element);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function initialize(): void
    {
        if ($this->initialized || !$this->mapping) {
            return;
        }

        $newObjects = [];

        if ($this->isDirty) {
            // Remember any NEW objects added through add()
            $newObjects = $this->coll->toArray();
        }

        $this->initialized = true;

        $this->coll->clear();
        $this->uow->loadCollection($this);
        $this->takeSnapshot();

        $this->dynamoData = [];

        // Reattach any NEW objects added through add()
        if (!$newObjects) {
            return;
        }

        foreach ($newObjects as $obj) {
            $this->coll->add($obj);
        }

        $this->isDirty = true;
    }

    public function isDirty(): bool
    {
        if ($this->isDirty) {
            return true;
        }

        if (!$this->initialized && count($this->coll)) {
            // not initialized collection with added elements
            return true;
        }

        if ($this->initialized) {
            // if initialized let's check with last known snapshot
            return $this->coll->toArray() !== $this->snapshot;
        }

        return false;
    }

    /**
     * @throws PersistentCollectionException
     */
    public function isEmpty(): bool
    {
        return $this->initialized ? $this->coll->isEmpty() : $this->count() === 0;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function setInitialized($bool): void
    {
        $this->initialized = $bool;
    }

    public function key()
    {
        return $this->coll->key();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function last()
    {
        $this->initialize();

        return $this->coll->last();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function map(Closure $func): BaseCollection
    {
        $this->initialize();

        return $this->coll->map($func);
    }

    public function next()
    {
        return $this->coll->next();
    }

    /**
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool
    {
        $this->initialize();

        return $this->coll->offsetExists($offset);
    }

    /**
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        $this->initialize();

        return $this->coll->offsetGet($offset);
    }

    /**
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!isset($offset)) {
            $this->doAdd($value, true);

            return;
        }

        $this->doSet($offset, $value, true);
    }

    /**
     * @throws PersistentCollectionException
     */
    #[ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        $this->doRemove($offset, true);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function partition(Closure $p): array
    {
        $this->initialize();

        return $this->coll->partition($p);
    }

    /* ArrayAccess implementation */

    public function reduce(Closure $func, $initial = null)
    {
        if (!method_exists($this->coll, 'reduce')) {
            throw new BadMethodCallException('reduce() is only available since doctrine/collections v2');
        }

        return $this->coll->reduce($func, $initial);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function remove($key)
    {
        return $this->doRemove($key, false);
    }

    /**
     * @throws PersistentCollectionException
     */
    public function removeElement($element): bool
    {
        $this->initialize();
        $removed = $this->coll->removeElement($element);

        if (!$removed) {
            return $removed;
        }

        $this->changed();

        return $removed;
    }

    public function set($key, $value): void
    {
        $this->doSet($key, $value, false);
    }

    public function setDirty($dirty): void
    {
        $this->isDirty = $dirty;
    }

    public function setDocumentManager(DocumentManager $dm): void
    {
        $this->dm = $dm;
        $this->uow = $dm->getUnitOfWork();
    }

    /**
     * @throws PersistentCollectionException
     */
    public function slice($offset, $length = null): array
    {
        $this->initialize();

        return $this->coll->slice($offset, $length);
    }

    public function takeSnapshot(): void
    {
        $this->snapshot = $this->coll->toArray();
        $this->isDirty = false;
    }

    /**
     * @throws PersistentCollectionException
     */
    public function toArray(): array
    {
        $this->initialize();

        return $this->coll->toArray();
    }

    public function unwrap(): BaseCollection
    {
        return $this->coll;
    }

    /**
     * Marks this collection as changed/dirty.
     */
    private function changed(): void
    {
        if ($this->isDirty) {
            return;
        }

        $this->isDirty = true;

        if ($this->owner === null || !$this->needsSchedulingForSynchronization()) {
            return;
        }

        $this->uow->scheduleForSynchronization($this->owner);
    }

    /**
     * Actual logic for adding an element to the collection.
     */
    private function doAdd(mixed $value, bool $arrayAccess): bool
    {
        $arrayAccess ? $this->coll->offsetSet(null, $value) : $this->coll->add($value);
        $this->changed();

        if ($value !== null && $this->isOrphanRemovalEnabled()) {
            $this->uow->unscheduleOrphanRemoval($value);
        }

        return true;
    }

    /**
     * Actual logic for removing element by its key.
     *
     * @throws PersistentCollectionException
     */
    private function doRemove(mixed $offset, bool $arrayAccess)
    {
        $this->initialize();

        if ($arrayAccess) {
            $this->coll->offsetUnset($offset);
            $removed = true;
        } else {
            $removed = $this->coll->remove($offset);
        }

        if (!$removed && !$arrayAccess) {
            return $removed;
        }

        $this->changed();

        return $removed;
    }

    /**
     * Actual logic for setting an element in the collection.
     */
    private function doSet(mixed $offset, mixed $value, bool $arrayAccess): void
    {
        $arrayAccess ? $this->coll->offsetSet($offset, $value) : $this->coll->set($offset, $value);

        // Handle orphanRemoval
        if ($value !== null && $this->isOrphanRemovalEnabled()) {
            $this->uow->unscheduleOrphanRemoval($value);
        }

        $this->changed();
    }

    /**
     * Returns whether or not this collection has orphan removal enabled.
     *
     * Embedded documents are automatically considered as "orphan removal enabled" because they might have references
     * that require to trigger cascade remove operations.
     */
    private function isOrphanRemovalEnabled(): bool
    {
        if ($this->mapping === null) {
            return false;
        }

        if (isset($this->mapping['embedded'])) {
            return true;
        }

        return isset($this->mapping['reference']) && $this->mapping['isOwningSide'] && $this->mapping['orphanRemoval'];
    }

    /**
     * Checks whether collection owner needs to be scheduled for dirty change in case the collection is modified.
     */
    private function needsSchedulingForSynchronization(): bool
    {
        return $this->owner !== null
            && $this->dm
            && !empty($this->mapping['isOwningSide'])
            && $this->dm->getClassMetadata(get_class($this->owner))->isChangeTrackingDeferredImplicit();
    }
}
