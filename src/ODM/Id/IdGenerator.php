<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Id;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;

interface IdGenerator
{
    public function generate(DocumentManager $dm, object $document): mixed;
}
