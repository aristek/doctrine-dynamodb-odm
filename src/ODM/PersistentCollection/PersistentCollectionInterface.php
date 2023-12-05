<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;

/**
 * Interface for persistent collection classes.
 *
 * @internal
 */
interface PersistentCollectionInterface extends Collection
{
    /**
     * Clears the internal snapshot information and sets isDirty to true if the collection has elements.
     */
    public function clearSnapshot(): void;

    /**
     * @return array<string, object>
     */
    public function getDeleteDiff(): array;

    /**
     * Get objects that were removed, unlike getDeleteDiff this doesn't care about indices.
     *
     * @return list<object>
     */
    public function getDeletedDocuments(): array;

    /**
     * Gets the array of raw dynamo data that will be used to initialize this collection.
     */
    public function getDynamoData(): array;

    /**
     * Get hints to account for during reconstitution/lookup of the documents.
     */
    public function getHints(): array;

    /**
     * @return array<string, object>
     */
    public function getInsertDiff(): array;

    /**
     * Get objects that were added, unlike getInsertDiff this doesn't care about indices.
     *
     * @return list<object>
     */
    public function getInsertedDocuments(): array;

    public function getMapping(): array;

    /**
     * Gets the collection owner.
     */
    public function getOwner(): ?object;

    /**
     * Returns the last snapshot of the elements in the collection.
     *
     * @return object[] The last snapshot of the elements.
     */
    public function getSnapshot(): array;

    /**
     * @throws DynamoDBException
     */
    public function getTypeClass(): ClassMetadata;

    /**
     * Initializes the collection by loading its contents from the database if the collection is not yet initialized.
     */
    public function initialize(): void;

    /**
     * Gets a boolean flag indicating whether this collection is dirty which means
     * its state needs to be synchronized with the database.
     *
     * @return bool TRUE if the collection is dirty, FALSE otherwise.
     */
    public function isDirty(): bool;

    /**
     * Checks whether this collection has been initialized.
     */
    public function isInitialized(): bool;

    /**
     * Sets a boolean flag, indicating whether this collection is dirty.
     *
     * @param bool $dirty Whether the collection should be marked dirty or not.
     */
    public function setDirty(bool $dirty): void;

    /**
     * Sets the document manager and unit of work (used during merge operations).
     */
    public function setDocumentManager(DocumentManager $dm): void;

    /**
     * Sets the array of raw dynamo data that will be used to initialize this collection.
     */
    public function setDynamoData(array $dynamoData): void;

    /**
     * Set hints to account for during reconstitution/lookup of the documents.
     */
    public function setHints(array $hints): void;

    /**
     * Sets the initialized flag of the collection, forcing it into that state.
     */
    public function setInitialized(bool $bool): void;

    /**
     * Sets the collection's owning document together with the AssociationMapping that
     * describes the association between the owner and the elements of the collection.
     */
    public function setOwner(object $document, array $mapping): void;

    /**
     * Tells this collection to take a snapshot of its current state reindexing
     * itself numerically if using save strategy that is enforcing BSON array.
     * Reindexing is safe as snapshot is taken only after synchronizing collection
     * with database or clearing it.
     */
    public function takeSnapshot(): void;

    /**
     * Returns the wrapped Collection instance.
     */
    public function unwrap(): Collection;
}
