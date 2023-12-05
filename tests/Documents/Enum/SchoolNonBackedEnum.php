<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum;

enum SchoolNonBackedEnum
{
    case Kindergarten;
    case Primary;
    case Middle;
    case High;
}
