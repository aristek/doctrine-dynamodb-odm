<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository;

use Aristek\Bundle\DynamodbBundle\ODM\Repository\DocumentRepository;

final class GameRepository extends DocumentRepository
{
    public function find(mixed $id): ?object
    {
        /** @var Game $document */
        $document = parent::find($id);
        $document->setName('Name Set on Custom Repository');

        return $document;
    }
}
