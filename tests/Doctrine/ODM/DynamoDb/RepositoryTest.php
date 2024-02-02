<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Game;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Repository\GameRepository;

final class RepositoryTest extends BaseTestCase
{
    public function testRepositoryFromContainer(): void
    {
        $game = (new Game())->setId('1')->setName('Game 1');

        $this->dm->persist($game);
        $this->dm->flush();
        $this->dm->clear();
        $repository = self::getContainer()->get(GameRepository::class);

        $game = $repository->find(new PrimaryKey('1', 'District'));

        self::assertInstanceOf(GameRepository::class, $repository);
        self::assertEquals('Name Set on Custom Repository', $game->getName());
    }
}
