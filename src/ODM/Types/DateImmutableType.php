<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use function sprintf;

class DateImmutableType extends DateType
{
    public static function getDateTime($value): DateTimeInterface
    {
        $datetime = parent::getDateTime($value);

        if ($datetime instanceof DateTimeImmutable) {
            return $datetime;
        }

        if ($datetime instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($datetime);
        }

        throw new RuntimeException(
            sprintf(
                '%s::getDateTime has returned an unsupported implementation of DateTimeInterface: %s',
                parent::class,
                $datetime::class,
            )
        );
    }

    public function getNextVersion(mixed $current): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
