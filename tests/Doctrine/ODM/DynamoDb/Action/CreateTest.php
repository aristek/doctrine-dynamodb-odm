<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Action;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\District;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Product;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\School;
use function array_map;

final class CreateTest extends BaseTestCase
{
    public function testAddNewCollectionItem(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $district = (new District())->setName('District')->setId('1');

        $this->dm->persist($district);
        $this->dm->persist($school1);
        $this->dm->persist($school2);

        $this->dm->flush();

        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));
        $school1 = $this->dm->find(School::class, new PrimaryKey($school1Id, 'School'));
        $school2 = $this->dm->find(School::class, new PrimaryKey($school2Id, 'School'));

        $district->addSchool($school1)->addSchool($school2);
        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        $district->getSchools()->count();

        self::assertCount(2, $district->getSchools());
    }

    public function testAddOneNewCollectionItem(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $district = (new District())->setName('District')->setId('1')->addSchool($school1);

        $this->dm->persist($district);
        $this->dm->persist($school2);

        $this->dm->flush();

        $school2Id = $school2->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(1, $district->getSchools());
        self::assertEquals('School 1', $district->getSchools()->first()->getName());

        $school2 = $this->dm->find(School::class, new PrimaryKey($school2Id, 'School'));

        $district->addSchool($school2);

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEquals('School 2', $district->getSchools()->last()->getName());
    }

    public function testClearCollection(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $district = (new District())
            ->setName('District')
            ->setId('1')
            ->addSchool($school1)
            ->addSchool($school2);

        $this->dm->persist($district);

        $this->dm->flush();

        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEqualsCanonicalizing(
            [$school1Id, $school2Id],
            array_map(static fn(School $school): string => $school->getId(), $district->getSchools()->toArray())
        );

        foreach ($district->getSchools() as $school) {
            $district->removeSchool($school);
        }

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(0, $district->getSchools());
    }

    public function testClearFullCollection(): void
    {
        $school1 = $this->createSchool(1)->setId('school_1');
        $school2 = $this->createSchool(2)->setId('school_2');
        $district = (new District())
            ->setName('District')
            ->setId('1')
            ->addSchool($school1)
            ->addSchool($school2);

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        /** @var District $district */
        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEqualsCanonicalizing(
            ['school_1', 'school_2'],
            array_map(static fn(School $school): string => $school->getId(), $district->getSchools()->toArray())
        );

        $district->getSchools()->clear();

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        /** @var District $district */
        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));
        self::assertCount(0, $district->getSchools());
    }

    public function testCreateCascade(): void
    {
        $school = $this->createSchool();
        $district = (new District())
            ->setName('District')
            ->addSchool($school);

        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();
        $schoolId = $district->getSchools()->first()->getId();

        $this->dm->clear();
        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));
        $school = $this->dm->find(School::class, new PrimaryKey($schoolId, 'School'));

        self::assertEquals($districtId, $district->getId());
        self::assertCount(1, $district->getSchools());
        self::assertEquals($schoolId, $school->getId());
        self::assertEquals($school->getId(), $district->getSchools()->first()->getId());
    }

    public function testCreateOne(): void
    {
        $district = (new District())->setName('District');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));

        self::assertEquals($districtId, $district->getId());
        self::assertEquals('District', $district->getName());
    }

    public function testCreateTwo(): void
    {
        $district = (new District())->setName('District');
        $product = (new Product())->setName('Product');

        $this->dm->persist($district);
        $this->dm->persist($product);
        $this->dm->flush();

        $districtId = $district->getId();
        $productId = $product->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey($districtId, 'District'));
        $product = $this->dm->find(Product::class, new PrimaryKey($productId, 'Product'));

        self::assertEquals($districtId, $district->getId());
        self::assertEquals('District', $district->getName());
        self::assertEquals($productId, $product->getId());
        self::assertEquals('Product', $product->getName());
    }

    public function testRemoveOneAndOneAddToCollection(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $school3 = $this->createSchool(3);
        $school3->setId('school_3');

        $district = (new District())
            ->setName('District')
            ->setId('1')
            ->addSchool($school1)
            ->addSchool($school2);

        $this->dm->persist($district);
        $this->dm->flush();

        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        /** @var District $district */
        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEqualsCanonicalizing(
            [$school1Id, $school2Id],
            array_map(static fn(School $school): string => $school->getId(), $district->getSchools()->toArray())
        );

        foreach ($district->getSchools() as $school) {
            if ($school->getId() === $school1Id) {
                $district->removeSchool($school);
            }
        }

        $district->addSchool($school3);

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEqualsCanonicalizing(
            [$school2Id, 'school_3'],
            array_map(static fn(School $school): string => $school->getId(), $district->getSchools()->toArray())
        );
    }

    public function testRemoveOneCollectionItem(): void
    {
        $school1 = $this->createSchool(1);
        $school2 = $this->createSchool(2);
        $district = (new District())
            ->setName('District')
            ->setId('1')
            ->addSchool($school1)
            ->addSchool($school2);

        $this->dm->persist($district);

        $this->dm->flush();

        $school1Id = $school1->getId();
        $school2Id = $school2->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(2, $district->getSchools());
        self::assertEqualsCanonicalizing(
            [$school1Id, $school2Id],
            array_map(static fn(School $school): string => $school->getId(), $district->getSchools()->toArray())
        );

        $school1 = $this->dm->find(School::class, new PrimaryKey($school1Id, 'School'));

        $district->removeSchool($school1);
        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertCount(1, $district->getSchools());
        self::assertEquals($school2Id, $district->getSchools()->first()->getId());
    }

    public function testUpsert(): void
    {
        $district = (new District())->setName('District')->setId('1');

        $this->dm->persist($district);
        $this->dm->flush();
        $this->dm->clear();

        $district = $this->dm->find(District::class, new PrimaryKey(1, 'District'));

        self::assertEquals(1, $district->getId());
        self::assertEquals('District', $district->getName());
    }
}
