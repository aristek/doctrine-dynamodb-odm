<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\ManagerRegistry;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\ServiceDocumentRepository;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Game;

final class GameRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function find(mixed $id): ?object
    {
        /** @var Game $document */
        $document = parent::find($id);
        $document->setName('Name Set on Custom Repository');

        return $document;
    }
}
