<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Annotation;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\District;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\School;

final class ReferenceTest extends BaseTestCase
{
    public function testCascadeMany(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);

        $district = (new District())->setName('District');
        $district->addSchool($school1)->addSchool($school2);

        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();
        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        /** @var School $school1 */
        $school1 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school1Id, 'School'));
        /** @var School $school2 */
        $school2 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school2Id, 'School'));
        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));

        self::assertEquals('School 1', $school1->getName());
        self::assertEquals('School 2', $school2->getName());
        self::assertEquals('District', $district->getName());
        self::assertCount(2, $district->getSchools());
    }

    public function testCascadeOne(): void
    {
        $district = (new District())->setName('District');
        $school = $this->createSchool();
        $school->setDistrict($district);

        $this->dm->persist($school);
        $this->dm->flush();

        $districtId = $district->getId();
        $schoolId = $school->getId();

        $this->dm->clear();

        /** @var School $school */
        $school = $this->dm->getRepository(School::class)->find(new PrimaryKey($schoolId, 'School'));
        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));

        self::assertEquals('School', $school->getName());
        self::assertEquals('District', $district->getName());
        self::assertEquals('District', $school->getDistrict()->getName());
    }

    public function testOrphanRemovalMany(): void
    {
        $district = (new District())->setName('District');
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);

        $district->addSchoolWithOrphanRemove($school1)->addSchoolWithOrphanRemove($school2);
        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();
        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));
        /** @var School $school */
        $school1 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school1Id, 'School'));
        /** @var School $school */
        $school2 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school2Id, 'School'));

        self::assertNotNull($district);
        self::assertCount(2, $district->getSchoolWithOrphanRemoves());
        self::assertNotNull($school1);
        self::assertNotNull($school2);

        $district->removeSchoolWithOrphanRemove($school1)->removeSchoolWithOrphanRemove($school2);

        $this->dm->flush();

        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));
        /** @var School $school */
        $school1 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school1Id, 'School'));
        /** @var School $school */
        $school2 = $this->dm->getRepository(School::class)->find(new PrimaryKey($school2Id, 'School'));

        self::assertNotNull($district);
        self::assertCount(0, $district->getSchoolWithOrphanRemoves());
        self::assertNull($school1);
        self::assertNull($school2);
    }

    public function testOrphanRemovalOne(): void
    {
        $district = (new District())->setName('District');
        $school = $this->createSchool();

        $district->setSchoolWithOrphanRemove($school);
        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();
        $schoolId = $school->getId();

        $this->dm->clear();

        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));
        /** @var School $school */
        $school = $this->dm->getRepository(School::class)->find(new PrimaryKey($schoolId, 'School'));

        self::assertNotNull($district);
        self::assertNotNull($school);

        $district->setSchoolWithOrphanRemove(null);

        $this->dm->flush();

        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));
        /** @var School $school */
        $school = $this->dm->getRepository(School::class)->find(new PrimaryKey($schoolId, 'School'));

        self::assertNotNull($district);
        /** @todo Fix */
        // self::assertNull($school);
    }

    public function testPersistOne(): void
    {
        $school = $this->createSchool();

        $this->dm->persist($school);
        $this->dm->flush();

        $schoolId = $school->getId();

        $this->dm->clear();

        /** @var School $school */
        $school = $this->dm->getRepository(School::class)->find(new PrimaryKey($schoolId, 'School'));

        self::assertEquals('School', $school->getName());
        self::assertEquals('private', $school->getType()->value);
        self::assertEquals(3, $school->getNumber()->value);
        self::assertNull($school->getNullableType());
        self::assertNull($school->getSchoolNonBacked());
        self::assertEquals('2024-01-01 10:00:00', $school->getDateTime()->format('Y-m-d H:i:s'));
        self::assertEquals('2024-01-01 11:00:00', $school->getDateTimeImmutable()->format('Y-m-d H:i:s'));
        self::assertEqualsCanonicalizing(['test1' => 1, 'test2' => '2'], $school->getArray());
        self::assertTrue($school->isBoolean());
        self::assertEquals(35.575, $school->getFloat());
        self::assertEquals(10, $school->getInt());
    }

    public function testPersistTwo(): void
    {
        $district = (new District())->setName('District');
        $school = $this->createSchool();

        $this->dm->persist($district);
        $this->dm->persist($school);
        $this->dm->flush();

        $districtId = $district->getId();
        $schoolId = $school->getId();

        $this->dm->clear();

        /** @var School $school */
        $school = $this->dm->getRepository(School::class)->find(new PrimaryKey($schoolId, 'School'));
        /** @var District $district */
        $district = $this->dm->getRepository(District::class)->find(new PrimaryKey($districtId, 'District'));

        self::assertEquals('School', $school->getName());
        self::assertEquals('District', $district->getName());
    }
}
