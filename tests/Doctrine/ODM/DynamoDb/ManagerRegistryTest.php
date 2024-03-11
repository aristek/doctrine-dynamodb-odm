<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\ManagerRegistry;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\District;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ManagerRegistryTest extends WebTestCase
{
    public function testResetting(): void
    {
        $district = (new District())
            ->setName('District 1')
            ->setId('123');

        /** @var DocumentManager $dm */
        $dm = self::getContainer()->get(DocumentManager::class);

        $dm->persist($district);

        self::assertCount(1, $dm->getUnitOfWork()->getScheduledDocumentUpserts());

        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = self::getContainer()->get(ManagerRegistry::class);
        $managerRegistry->resetManager();

        $dm->getUnitOfWork();

        self::assertCount(0, $dm->getUnitOfWork()->getScheduledDocumentUpserts());
    }
}
