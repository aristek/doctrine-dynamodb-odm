<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Annotation;

use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded\Bar;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded\Coordinate;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded\Location;
use function array_map;

final class EmbedTest extends BaseTestCase
{
    public function testClearEmbedElementFromDocumentWithManyEmbed(): void
    {
        $location1 = (new Location())->setName('Location 1');
        $location2 = (new Location())->setName('Location 2');

        $bar = (new Bar())
            ->setName('Bar')
            ->addLocation($location1)
            ->addLocation($location2);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(2, $bar->getLocations());

        $firstLocation = $bar->getLocations()->first();
        $lastLocation = $bar->getLocations()->last();

        self::assertEquals('Location 1', $firstLocation->getName());
        self::assertEquals('Location 2', $lastLocation->getName());

        $bar->getLocations()->clear();

        $this->dm->persist($bar);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(0, $bar->getLocations());
    }

    public function testCreateEmbedMany(): void
    {
        $location1 = (new Location())->setName('Location 1');
        $location2 = (new Location())->setName('Location 2');

        $bar = (new Bar())
            ->setName('Bar')
            ->addLocation($location1)
            ->addLocation($location2);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(2, $bar->getLocations());
        self::assertEquals('Location 1', $bar->getLocations()->first()->getName());
        self::assertEquals('Location 2', $bar->getLocations()->last()->getName());
    }

    public function testCreateEmbedOne(): void
    {
        $parentLocation = (new Location())->setName('Parent Location');
        $location = (new Location())->setName('Location')->setParent($parentLocation);

        $bar = (new Bar())
            ->setName('Bar')
            ->setSingleLocation($location);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(0, $bar->getLocations());
        self::assertEquals('Location', $bar->getSingleLocation()->getName());
        self::assertEquals('Parent Location', $bar->getSingleLocation()->getParent()->getName());
    }

    public function testCreateEmbedWithReferenceMany(): void
    {
        $coordinate1 = (new Coordinate())->setX(1)->setY(1);
        $coordinate2 = (new Coordinate())->setX(2)->setY(2);
        $coordinate3 = (new Coordinate())->setX(3)->setY(3);
        $coordinate4 = (new Coordinate())->setX(4)->setY(4);

        $location1 = (new Location())->setName('Location 1')->addCoordinate($coordinate1);
        $location2 = (new Location())->setName('Location 2')->addCoordinate($coordinate2);
        $location3 = (new Location())->setName('Location 3')->setCoordinates([$coordinate3, $coordinate4]);

        $bar = (new Bar())
            ->setName('Bar')
            ->addLocation($location1)
            ->addLocation($location2)
            ->addLocation($location3);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertNotNull($bar);
        self::assertCount(3, $bar->getLocations());

        foreach ($bar->getLocations() as $location) {
            switch ($location->getName()) {
                case 'Location 1':
                    self::assertCount(1, $location->getCoordinates());
                    self::assertEquals(1, $location->getCoordinates()->first()->getX());
                    self::assertEquals(1, $location->getCoordinates()->first()->getY());
                    break;

                case 'Location 2':
                    self::assertCount(1, $location->getCoordinates());
                    self::assertEquals(2, $location->getCoordinates()->first()->getX());
                    self::assertEquals(2, $location->getCoordinates()->first()->getY());
                    break;

                case 'Location 3':
                    self::assertCount(2, $location->getCoordinates());
                    self::assertEqualsCanonicalizing(
                        [3, 4],
                        array_map(
                            static fn(Coordinate $coordinate): int => $coordinate->getX(),
                            $location->getCoordinates()->toArray()
                        )
                    );
                    self::assertEqualsCanonicalizing(
                        [3, 4],
                        array_map(
                            static fn(Coordinate $coordinate): int => $coordinate->getY(),
                            $location->getCoordinates()->toArray()
                        )
                    );
                    break;
            }
        }
    }

    public function testCreateEmbedWithReferenceOne(): void
    {
        $coordinate = (new Coordinate())->setX(1)->setY(1);
        $location = (new Location())->setName('Location')->setCurrentCoordination($coordinate);
        $bar = (new Bar())->setName('Bar')->setSingleLocation($location);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertNotNull($bar);

        $location = $bar->getSingleLocation();

        self::assertNotNull($location);

        $coordinate = $location->getCurrentCoordination();

        self::assertNotNull($location);
        self::assertEquals(1, $coordinate->getX());
        self::assertEquals(1, $coordinate->getY());
    }

    public function testRemoveEmbedElementFromDocumentWithManyEmbed(): void
    {
        $location1 = (new Location())->setName('Location 1');
        $location2 = (new Location())->setName('Location 2');

        $bar = (new Bar())
            ->setName('Bar')
            ->addLocation($location1)
            ->addLocation($location2);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(2, $bar->getLocations());

        $firstLocation = $bar->getLocations()->first();
        $lastLocation = $bar->getLocations()->last();

        self::assertEquals('Location 1', $firstLocation->getName());
        self::assertEquals('Location 2', $lastLocation->getName());

        $bar->removeLocation($firstLocation);

        $this->dm->persist($bar);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(1, $bar->getLocations());
        self::assertEquals('Location 2', $bar->getLocations()->first()->getName());
    }

    public function testUpdateDocumentWithEmbed(): void
    {
        $parentLocation = (new Location())->setName('Parent Location');
        $location = (new Location())->setName('Location')->setParent($parentLocation);

        $bar = (new Bar())
            ->setName('Bar')
            ->setSingleLocation($location);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(0, $bar->getLocations());
        self::assertEquals('Location', $bar->getSingleLocation()->getName());
        self::assertEquals('Parent Location', $bar->getSingleLocation()->getParent()->getName());

        $bar->getSingleLocation()->setName('New Location');

        $this->dm->persist($bar);
        $this->dm->flush();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);
        self::assertEquals('New Location', $bar->getSingleLocation()->getName());
    }

    public function testUpdateDocumentWithManyEmbed(): void
    {
        $location1 = (new Location())->setName('Location 1');
        $location2 = (new Location())->setName('Location 2');

        $bar = (new Bar())
            ->setName('Bar')
            ->addLocation($location1)
            ->addLocation($location2);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(2, $bar->getLocations());

        $firstLocation = $bar->getLocations()->first();
        $lastLocation = $bar->getLocations()->last();

        self::assertEquals('Location 1', $firstLocation->getName());
        self::assertEquals('Location 2', $lastLocation->getName());

        $firstLocation->setName('New Location First');
        $lastLocation->setName('New Location Last');

        $this->dm->persist($bar);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertCount(2, $bar->getLocations());
        self::assertEquals('New Location First', $bar->getLocations()->first()->getName());
        self::assertEquals('New Location Last', $bar->getLocations()->last()->getName());
    }

    public function testUpdateEmbedWithReferenceOne(): void
    {
        $coordinate = (new Coordinate())->setX(1)->setY(1);
        $location = (new Location())->setName('Location')->setCurrentCoordination($coordinate);
        $bar = (new Bar())->setName('Bar')->setSingleLocation($location);

        $this->dm->persist($bar);
        $this->dm->flush();

        $barId = $bar->getId();

        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        self::assertNotNull($bar);

        $location = $bar->getSingleLocation();

        self::assertNotNull($location);

        $coordinate = $location->getCurrentCoordination();

        self::assertNotNull($location);
        self::assertEquals(1, $coordinate->getX());
        self::assertEquals(1, $coordinate->getY());

        $coordinate->setX(10)->setY(20);

        $this->dm->persist($bar);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($barId);

        $location = $bar->getSingleLocation();

        self::assertNotNull($location);

        $coordinate = $location->getCurrentCoordination();

        self::assertNotNull($location);
        self::assertEquals(10, $coordinate->getX());
        self::assertEquals(20, $coordinate->getY());
    }

    public function testUpsertEmbedElementToDocument(): void
    {
        $location = (new Location())->setName('Location 1');

        $bar = (new Bar())
            ->setId('1')
            ->setName('Bar')
            ->setSingleLocation($location);

        $this->dm->persist($bar);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Bar $bar */
        $bar = $this->dm->getRepository(Bar::class)->find($bar->getId());

        self::assertEquals('Location 1', $bar->getSingleLocation()->getName());
    }
}
