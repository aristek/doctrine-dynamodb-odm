<?php

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum;

enum SchoolTypeEnum: string
{
    case General = 'general';
    case Private = 'private';
}
