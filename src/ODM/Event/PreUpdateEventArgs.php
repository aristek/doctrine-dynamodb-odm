<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Event;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use InvalidArgumentException;
use function get_class;
use function sprintf;

/**
 * Class that holds event arguments for a preUpdate event.
 */
final class PreUpdateEventArgs extends LifecycleEventArgs
{
    private array $documentChangeSet;

    public function __construct(object $document, DocumentManager $dm, array $changeSet)
    {
        parent::__construct($document, $dm);

        $this->documentChangeSet = $changeSet;
    }

    public function getDocumentChangeSet(): array
    {
        return $this->documentChangeSet;
    }

    /**
     * Gets the new value of the changeset of the changed field.
     */
    public function getNewValue(string $field): mixed
    {
        $this->assertValidField($field);

        return $this->documentChangeSet[$field][1];
    }

    /**
     * Gets the old value of the changeset of the changed field.
     */
    public function getOldValue(string $field): mixed
    {
        $this->assertValidField($field);

        return $this->documentChangeSet[$field][0];
    }

    public function hasChangedField(string $field): bool
    {
        return isset($this->documentChangeSet[$field]);
    }

    /**
     * Sets the new value of this field.
     */
    public function setNewValue(string $field, mixed $value): void
    {
        $this->assertValidField($field);

        $this->documentChangeSet[$field][1] = $value;
        $this->getDocumentManager()->getUnitOfWork()->setDocumentChangeSet(
            $this->getDocument(),
            $this->documentChangeSet
        );
    }

    /**
     * Asserts the field exists in changeset.
     *
     * @throws InvalidArgumentException If the field has no changeset.
     */
    private function assertValidField(string $field): void
    {
        if (!isset($this->documentChangeSet[$field])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Field "%s" is not a valid field of the document "%s" in PreUpdateEventArgs.',
                    $field,
                    get_class($this->getDocument()),
                )
            );
        }
    }
}
