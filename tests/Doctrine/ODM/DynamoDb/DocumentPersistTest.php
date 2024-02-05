<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb;

use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Game;

final class DocumentPersistTest extends BaseTestCase
{
    public function testExists(): void
    {
        $game = (new Game())->setId('1')->setName('Game 1');

        $this->dm->persist($game);
        $this->dm->flush();
        $this->dm->clear();

        self::assertTrue($this->dm->getUnitOfWork()->getDocumentPersister(Game::class)->exists($game));
    }

    public function testRefresh(): void
    {
        $game = (new Game())->setId('1')->setName('Game 1');

        $this->dm->persist($game);
        $this->dm->flush();

        $game->setName('New Name');

        self::assertEquals('New Name', $game->getName());

        $this->dm->getUnitOfWork()->getDocumentPersister(Game::class)->refresh($game);

        self::assertNotEquals('New Name', $game->getName());
    }
}
