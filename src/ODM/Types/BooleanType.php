<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

class BooleanType extends Type
{
    public function closureToPHP(): string
    {
        return '$return = (bool) $value;';
    }

    public function convertToDatabaseValue(mixed $value): ?bool
    {
        return $value !== null ? (bool) $value : null;
    }

    public function convertToPHPValue($value): ?bool
    {
        return $value !== null ? (bool) $value : null;
    }
}
