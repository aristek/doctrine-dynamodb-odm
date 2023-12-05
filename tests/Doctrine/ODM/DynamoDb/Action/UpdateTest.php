<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Action;

use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded\Bar;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded\Location;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\ReadOnlyItem;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\District;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\School;

final class UpdateTest extends BaseTestCase
{
    public function testReadOnly(): void
    {
        $dynamoDbManager = $this->dm->getQueryBuilder(ReadOnlyItem::class)->getDynamoDbManager();
        $item = ['pk' => 'ReadOnlyItem', 'sk' => 'ROI#1', 'id' => '1', 'name' => 'Test Name'];
        $dynamoDbManager->insertOne($item, $this->dm->getConfiguration()->getDatabase());

        $readOnlyItem = $this->dm->getRepository(ReadOnlyItem::class)->find('1');

        self::assertNotNull($readOnlyItem);
        self::assertEquals('Test Name', $readOnlyItem->getName());

        $readOnlyItem->setName('New Name');
        $this->dm->persist($readOnlyItem);
        $this->dm->flush();
        $this->dm->clear();

        $readOnlyItem = $this->dm->getRepository(ReadOnlyItem::class)->find('1');

        self::assertNotEquals('New Name', $readOnlyItem->getName());
    }

    public function testUpdateDocument(): void
    {
        $district = (new District())->setName('District')->setId('1');
        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->getRepository(District::class)->find('1');

        self::assertNotNull($district);
        self::assertEquals('District', $district->getName());

        $district->setName('New District');

        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->getRepository(District::class)->find('1');

        self::assertEquals('New District', $district->getName());
    }

    public function testUpdateDocumentCascade(): void
    {
        $school = $this->createSchool(1);
        $district = (new District())->setName('District')->setId('1')->addSchool($school);
        $this->dm->persist($district);
        $this->dm->flush();

        $schoolId = $school->getId();

        $this->dm->clear();

        $school = $this->dm->getRepository(School::class)->find($schoolId);

        self::assertNotNull($school);
        self::assertEquals('School 1', $school->getName());

        $school->setName('New School');

        $this->dm->flush();
        $this->dm->clear();

        $school = $this->dm->getRepository(School::class)->find($schoolId);

        self::assertEquals('New School', $school->getName());
    }
}
