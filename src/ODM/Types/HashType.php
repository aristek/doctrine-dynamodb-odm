<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

use Aristek\Bundle\DynamodbBundle\ODM\DynamoDBException;
use function is_array;

/**
 * The Hash type.
 */
class HashType extends Type
{
    /**
     * @throws DynamoDBException
     */
    public function convertToDatabaseValue(mixed $value): ?object
    {
        if ($value !== null && !is_array($value)) {
            throw DynamoDBException::invalidValueForType('Hash', ['array', 'null'], $value);
        }

        return $value !== null ? (object) $value : null;
    }

    public function convertToPHPValue($value): ?array
    {
        return $value !== null ? (array) $value : null;
    }
}
