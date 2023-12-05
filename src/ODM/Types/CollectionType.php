<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use function array_values;
use function is_array;

/**
 * The Collection type.
 */
class CollectionType extends Type
{
    /**
     * @throws DynamoDBException
     */
    public function convertToDatabaseValue(mixed $value): mixed
    {
        if ($value !== null && !is_array($value)) {
            throw DynamoDBException::invalidValueForType('Collection', ['array', 'null'], $value);
        }

        return $value !== null ? array_values($value) : null;
    }

    public function convertToPHPValue($value): mixed
    {
        return $value !== null ? array_values($value) : null;
    }
}
