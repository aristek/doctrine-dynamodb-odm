<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Action;

use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository\Game;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository\GameRepository;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository\GameWithFakeRepository;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository\User;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\District;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\School;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\WithMapping\Affiliate;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\WithMapping\Organization;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use LogicException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use function array_map;

final class ReadTest extends BaseTestCase
{
    public function testFindOneBy(): void
    {
        $district1 = (new District())->setId('1')->setName('District 1');
        $district2 = (new District())->setId('2')->setName('District 2');
        $district3 = (new District())->setId('3')->setName('District 3');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->persist($district3);
        $this->dm->flush();
        $this->dm->clear();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;
        $district = $repository->findOneBy(['id' => 1]);

        self::assertEquals('District 1', $district->getName());
    }

    public function testFindOneByWithoutId(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;

        $this->expectException(InvalidArgumentException::class);

        $repository->findOneBy(['test' => '1']);
    }

    public function testFindWithIdAsArray(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;

        $district = $repository->find(['id' => '1']);

        self::assertEquals('District 1', $district->getName());
    }

    public function testFindWithNullId(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;

        $district = $repository->find(null);

        self::assertNull($district);
    }

    public function testFindWithoutId(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;

        $this->expectException(InvalidArgumentException::class);

        $repository->find(['test' => '1']);
    }

    public function testMatching(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $repository = $this->dm->getRepository(District::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Not Supported.');

        $repository->matching(new Criteria());
    }

    public function testPagination(): void
    {
        $district1 = (new District())->setId('1')->setName('District 1');
        $district2 = (new District())->setId('2')->setName('District 2');
        $district3 = (new District())->setId('3')->setName('District 3');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->persist($district3);
        $this->dm->flush();
        $this->dm->clear();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;
        $districts = $repository->findAll();

        self::assertCount(3, $districts);

        $this->dm->clear();

        $oneDistrict = $districtRepository->findBy([], limit: 1);

        self::assertCount(1, $oneDistrict);
        self::assertEquals(1, $oneDistrict[0]->getId());

        $nextDistricts = $districtRepository->findBy(criteria: [], limit: 2, after: $oneDistrict[0]);

        self::assertCount(2, $nextDistricts);
        self::assertEqualsCanonicalizing(
            ['2', '3'],
            array_map(static fn(District $district): string => $district->getId(), $nextDistricts)
        );
    }

    public function testReadAll(): void
    {
        $district1 = (new District())->setName('District 1');
        $district2 = (new District())->setName('District 2');
        $school = (new School())->setName('School');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->persist($school);
        $this->dm->flush();
        $this->dm->clear();

        $repository = $this->dm->getRepository(District::class);
        $districts = $repository->findAll();

        self::assertCount(2, $districts);
        self::assertEqualsCanonicalizing(
            ['District 1', 'District 2'],
            array_map(static fn(District $district): string => $district->getName(), $districts)
        );
    }

    public function testReadFromCache(): void
    {
        $district1 = (new District())->setId('1')->setName('District 1');
        $district2 = (new District())->setId('2')->setName('District 2');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->flush();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;
        $district1 = $repository->findOneBy(['id' => '1']);
        $district2 = $repository->find('2');

        self::assertEquals('District 1', $district1->getName());
        self::assertEquals('District 2', $district2->getName());
    }

    public function testReadFromCustomRepository(): void
    {
        $game = (new Game())->setId('1')->setName('Game 1');

        $this->dm->persist($game);
        $this->dm->flush();
        $this->dm->clear();

        $repository = $this->dm->getRepository(Game::class);

        $game = $repository->find('1');

        self::assertInstanceOf(GameRepository::class, $repository);
        self::assertEquals('Name Set on Custom Repository', $game->getName());
    }

    public function testReadFromCustomRepositoryWithCustomRepositoryMethod(): void
    {
        $user1 = (new User())->setId('1')->setName('Player 1')->setRole('player');
        $user2 = (new User())->setId('2')->setName('Admin 1')->setRole('admin');
        $user3 = (new User())->setId('3')->setName('Player 3')->setRole('player');
        $game = (new Game())
            ->setId('1')
            ->setName('Game 1')
            ->addUser($user1)
            ->addUser($user2)
            ->addUser($user3);

        $this->dm->persist($game);
        $this->dm->flush();
        $this->dm->clear();

        $repository = $this->dm->getRepository(Game::class);

        $game = $repository->find('1');

        self::assertCount(3, $game->getUsers());
        self::assertCount(1, $game->getAdminUsers());

        /** @var User $adminUser */
        $adminUser = $game->getAdminUsers()->first();

        self::assertEquals('admin', $adminUser->getRole());
        self::assertEquals('Admin 1', $adminUser->getName());
    }

    public function testReadFromFakeCustomRepository(): void
    {
        $game = (new GameWithFakeRepository)->setId('1')->setName('Game 1');

        $this->dm->persist($game);
        $this->dm->flush();
        $this->dm->clear();

        $this->expectException(MappingException::class);

        $this->dm->getRepository(GameWithFakeRepository::class);
    }

    public function testReadFromQueryBuilder(): void
    {
        $district = (new District())->setId('1')->setName('District 1');

        $this->dm->persist($district);
        $this->dm->flush();

        $qb = $this->dm->getRepository(District::class)->createQueryBuilder();
        $district = $qb->find(['pk' => 'District', 'sk' => 'D#1']);

        self::assertEquals('District 1', $district->getName());
    }

    public function testReadLimit(): void
    {
        $district1 = (new District())->setName('District 1');
        $district2 = (new District())->setName('District 2');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->flush();
        $this->dm->clear();

        $repository = $this->dm->getRepository(District::class);
        $districts = $repository->findAll();

        self::assertCount(2, $districts);
        $this->dm->clear();

        $oneDistrict = $this->dm->getRepository(District::class)->findBy([], limit: 1);

        self::assertCount(1, $oneDistrict);
    }

    public function testReadOne(): void
    {
        $district = (new District())->setName('District');

        $this->dm->persist($district);
        $this->dm->flush();

        $districtId = $district->getId();

        $this->dm->clear();

        $district = $this->dm->find(District::class, $districtId);

        self::assertEquals($districtId, $district->getId());
        self::assertEquals('District', $district->getName());
    }

    public function testReadWithMappedBy(): void
    {
        $affiliate1 = (new Affiliate())->setId('1')->setName('Affiliate 1');
        $affiliate2 = (new Affiliate())->setId('2')->setName('Affiliate 2');
        $organization = (new Organization())
            ->setId('1')
            ->setName('Organization')
            ->addAffiliate($affiliate1)
            ->addAffiliate($affiliate2);

        $this->dm->persist($organization);
        $this->dm->flush();
        $this->dm->clear();

        $organization = $this->dm->getRepository(Organization::class)->find('1');
        $affiliate1 = $this->dm->getRepository(Affiliate::class)->find('1');

        self::assertNotNull($organization);
        self::assertCount(2, $organization->getAffiliates());
        self::assertNotNull($affiliate1);
        self::assertNotNull($affiliate2);
        self::assertEqualsCanonicalizing(
            ['1', '2'],
            array_map(
                static fn(Affiliate $affiliate): string => $affiliate->getId(),
                $organization->getAffiliates()->toArray()
            )
        );
        self::assertEquals('1', $affiliate1->getOrganization()->getId());
        self::assertEquals('1', $affiliate2->getOrganization()->getId());

        $organization->removeAffiliate($affiliate1);
        $this->dm->persist($organization);
        $this->dm->flush();
        /** @todo error */
    }

    public function testSortOrder(): void
    {
        $district1 = (new District())->setId('1')->setName('District 1');
        $district2 = (new District())->setId('2')->setName('District 2');
        $district3 = (new District())->setId('3')->setName('District 3');

        $this->dm->persist($district1);
        $this->dm->persist($district2);
        $this->dm->persist($district3);
        $this->dm->flush();
        $this->dm->clear();

        $districtRepository = $this->dm->getRepository(District::class);
        $repository = $districtRepository;
        $districts = $repository->findBy([]);

        self::assertCount(3, $districts);
        self::assertEqualsCanonicalizing(
            ['1', '2', '3'],
            array_map(static fn(District $district): string => $district->getId(), $districts)
        );

        $this->dm->clear();

        $districts = $districtRepository->findBy([], orderBy: [Criteria::DESC]);

        self::assertCount(3, $districts);
        self::assertEqualsCanonicalizing(
            ['3', '2', '1'],
            array_map(static fn(District $district): string => $district->getId(), $districts)
        );
    }
}
