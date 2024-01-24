<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM;

use Aristek\Bundle\DynamodbBundle\ODM\Repository\ObjectRepositoryInterface;
use Exception;
use function array_slice;
use function end;
use function implode;
use function is_array;
use function is_object;
use function sprintf;

class DynamoDBException extends Exception
{
    public static function cannotPersistMappedSuperclass(string $className): self
    {
        return new self(
            sprintf('Cannot persist object of class "%s" as it is not a persistable document.', $className)
        );
    }

    public static function cannotRefreshDocument(): self
    {
        return new self('Failed to fetch current data of document being refreshed. Was it removed in the meantime?');
    }

    public static function commitInProgress(): self
    {
        return new self('There is already a commit operation in progress. Did you call flush from an event listener?');
    }

    public static function detachedDocumentCannotBeRemoved(): self
    {
        return new self('Detached document cannot be removed');
    }

    public static function documentManagerClosed(): self
    {
        return new self('The DocumentManager is closed.');
    }

    public static function invalidDocumentRepository(string $className): self
    {
        return new self(
            sprintf("Invalid repository class '%s'. It must be a %s.", $className, ObjectRepositoryInterface::class)
        );
    }

    public static function invalidDocumentState(int $state): self
    {
        return new self(sprintf('Invalid document state "%s"', $state));
    }

    public static function invalidValueForType(string $type, array|string $expected, mixed $got): self
    {
        if (is_array($expected)) {
            $expected = sprintf(
                '%s or %s',
                implode(', ', array_slice($expected, 0, -1)),
                end($expected),
            );
        }

        if (is_object($got)) {
            $gotType = $got::class;
        } elseif (is_array($got)) {
            $gotType = 'array';
        } else {
            $gotType = 'scalar';
        }

        return new self(sprintf('%s type requires value of type %s, %s given', $type, $expected, $gotType));
    }
}
