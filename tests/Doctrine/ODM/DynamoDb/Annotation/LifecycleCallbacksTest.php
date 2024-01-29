<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Annotation;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\LifecycleCallbacks\User;

final class LifecycleCallbacksTest extends BaseTestCase
{
    public function testLifecycleCallbacks(): void
    {
        $user = new User();

        self::assertNull($user->getCreatedAt());
        self::assertNull($user->getPrePersistOther());
        self::assertNull($user->getPostPersist());
        self::assertNull($user->getPreLoad());
        self::assertNull($user->getPostLoad());
        self::assertNull($user->getPreUpdate());
        self::assertNull($user->getPreFlush());

        $this->dm->persist($user);

        self::assertEquals('2024-01-01 10:00:00', $user->getCreatedAt()->format('Y-m-d H:i:s'));
        self::assertEquals('PrePersistOther', $user->getPrePersistOther());
        self::assertNull($user->getPostPersist());
        self::assertNull($user->getPreLoad());
        self::assertNull($user->getPostLoad());
        self::assertNull($user->getPreUpdate());
        self::assertNull($user->getPreFlush());

        $this->dm->flush();

        self::assertEquals('PostPersist', $user->getPostPersist());
        self::assertEquals('PreFlush', $user->getPreFlush());
        self::assertNull($user->getPreLoad());
        self::assertNull($user->getPostLoad());
        self::assertNull($user->getPreUpdate());

        $userId = $user->getId();
        $this->dm->clear();

        $user = $this->dm->getRepository(User::class)->find(new PrimaryKey($userId, 'User'));
        self::assertEquals('PostLoad', $user->getPostLoad());
        self::assertEquals('PreLoad', $user->getPreLoad());

        #TODO Not Processed
        self::assertNull($user->getPreUpdate());
    }
}
