<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum;

enum SchoolNumberIntEnum: int
{
    case Kindergarten = 1;
    case Primary = 2;
    case Middle = 3;
    case High = 4;
}
