<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query;

final class Query
{
    public const HINT_READ_ONLY = 5;
    public const HINT_READ_PREFERENCE = 3;
    public const HINT_REFRESH = 1;
}
