<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Annotation;

use Aristek\Bundle\DynamodbBundle\ODM\Id\Index;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\MappedSuperclass\Client;

final class MappedSuperclassTest extends BaseTestCase
{
    public function testMappedSuperclass(): void
    {
        #TODO Not Work with ID in MappedSuperclass
        $client = (new Client())
            ->setFirstName('John')
            ->setLastName('Dow')
            ->setPhone('123123123');

        $this->dm->persist($client);
        $this->dm->flush();

        $clientId = $client->getId();

        $this->dm->clear();

        $client = $this->dm->find(Client::class, new Index($clientId, 'Client'));

        self::assertEquals('John', $client->getFirstName());
        self::assertEquals('Dow', $client->getLastName());
        self::assertEquals('123123123', $client->getPhone());
    }
}
