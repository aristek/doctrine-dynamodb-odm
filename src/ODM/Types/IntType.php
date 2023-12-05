<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

class IntType extends Type implements Incrementable
{
    public function closureToPHP(): string
    {
        return '$return = (int) $value;';
    }

    public function convertToDatabaseValue(mixed $value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    public function convertToPHPValue($value): ?int
    {
        return $value !== null ? (int) $value : null;
    }

    public function diff(mixed $old, mixed $new): mixed
    {
        return $new - $old;
    }
}
