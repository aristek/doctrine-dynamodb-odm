<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use Throwable;
use function sprintf;

/**
 * DynamoDB ODM PersistentCollection Exception.
 */
final class PersistentCollectionException extends DynamoDBException
{
    public static function directoryNotWritable(): self
    {
        return new self('Your PersistentCollection directory must be writable.');
    }

    public static function directoryRequired(): self
    {
        return new self('You must configure a PersistentCollection directory. See docs for details.');
    }

    public static function globalSecondaryIndexRequiredToLoadCollection(): self
    {
        return new self('Cannot load persistent collection without GSI.');
    }

    public static function invalidParameterTypeHint(
        string $className,
        string $methodName,
        string $parameterName,
        ?Throwable $previous = null,
    ): self {
        return new self(
            sprintf(
                'The type hint of parameter "%s" in method "%s" in class "%s" is invalid.',
                $parameterName,
                $methodName,
                $className,
            ),
            0,
            $previous,
        );
    }

    public static function invalidReturnTypeHint(
        string $className,
        string $methodName,
        ?Throwable $previous = null
    ): self {
        return new self(
            sprintf(
                'The return type of method "%s" in class "%s" is invalid.',
                $methodName,
                $className,
            ),
            0,
            $previous,
        );
    }

    public static function namespaceRequired(): self
    {
        return new self('You must configure a PersistentCollection namespace. See docs for details');
    }

    public static function ownerRequiredToLoadCollection(): self
    {
        return new self('Cannot load persistent collection without an owner.');
    }

    public static function parentClassRequired(string $className, string $methodName): self
    {
        return new self(
            sprintf(
                'The method "%s" in class "%s" defines a parent return type, but the class does not extend any class.',
                $methodName,
                $className,
            ),
        );
    }
}
