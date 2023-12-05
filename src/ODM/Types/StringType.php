<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

class StringType extends Type
{
    public function closureToPHP(): string
    {
        return '$return = (string) $value;';
    }

    public function convertToDatabaseValue(mixed $value): ?string
    {
        return $value === null ? $value : (string) $value;
    }

    public function convertToPHPValue($value): ?string
    {
        return $value !== null ? (string) $value : null;
    }
}
