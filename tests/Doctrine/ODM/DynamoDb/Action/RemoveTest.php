<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Action;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\District;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\School;

final class RemoveTest extends BaseTestCase
{
    public function testCascadeRemove(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $district = (new District())
            ->setName('District')
            ->addSchool($school1)
            ->addSchool($school2);

        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();
        $school1Id = $district->getSchools()->first()->getId();
        $school2Id = $district->getSchools()->last()->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));
        $school1 = $this->dm->find(School::class, new PrimaryKey($school1Id, 'School'));
        $school2 = $this->dm->find(School::class, new PrimaryKey($school2Id, 'School'));

        self::assertNotNull($district);
        self::assertNotNull($school1);
        self::assertNotNull($school2);
        self::assertCount(2, $district->getSchools());

        $this->dm->remove($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));
        $school1 = $this->dm->find(School::class, new PrimaryKey($school1Id, 'School'));
        $school2 = $this->dm->find(School::class, new PrimaryKey($school2Id, 'School'));

        self::assertNull($district);
        self::assertNull($school1);
        self::assertNull($school2);
    }

    public function testRemove(): void
    {
        $district = (new District())->setName('District');
        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));

        self::assertEquals('District', $district->getName());

        $this->dm->remove($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));

        self::assertNull($district);
    }
}
