<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Iterator;

interface Iterator extends \Iterator
{
    public function toArray(): array;
}
