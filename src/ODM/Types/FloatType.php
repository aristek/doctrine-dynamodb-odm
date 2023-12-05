<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

class FloatType extends Type implements Incrementable
{
    public function closureToPHP(): string
    {
        return '$return = (float) $value;';
    }

    public function convertToDatabaseValue(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    public function convertToPHPValue($value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    public function diff(mixed $old, mixed $new): mixed
    {
        return $new - $old;
    }
}
