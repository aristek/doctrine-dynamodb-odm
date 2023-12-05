<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Hydrator;

use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use function sprintf;

/**
 * DynamoDB ODM Hydrator Exception
 */
final class HydratorException extends DynamoDBException
{
    public static function associationItemTypeMismatch(
        string $className,
        string $fieldName,
        int|string $key,
        string $expectedType,
        string $actualType
    ): self {
        return new self(
            sprintf(
                'Expected association item with key "%s" for field "%s" in document of type "%s" to be of type "%s", "%s" received.',
                $key,
                $fieldName,
                $className,
                $expectedType,
                $actualType,
            )
        );
    }

    public static function associationTypeMismatch(
        string $className,
        string $fieldName,
        string $expectedType,
        string $actualType
    ): self {
        return new self(
            sprintf(
                'Expected association for field "%s" in document of type "%s" to be of type "%s", "%s" received.',
                $fieldName,
                $className,
                $expectedType,
                $actualType,
            )
        );
    }

    public static function hydratorDirectoryNotWritable(): self
    {
        return new self('Your hydrator directory must be writable.');
    }

    public static function hydratorDirectoryRequired(): self
    {
        return new self('You must configure a hydrator directory. See docs for details.');
    }

    public static function hydratorNamespaceRequired(): self
    {
        return new self('You must configure a hydrator namespace. See docs for details');
    }
}
