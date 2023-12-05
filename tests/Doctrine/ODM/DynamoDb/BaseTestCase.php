<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb;

use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolNonBackedEnum;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolNumberIntEnum;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolTypeEnum;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\School;
use DateTime;
use DateTimeImmutable;
use Exception;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use function dump;

class BaseTestCase extends WebTestCase
{
    protected DocumentManager $dm;

    protected function createSchool(int $index = 0): School
    {
        $postfix = '';

        if ($index) {
            $postfix = ' '.$index;
        }

        return (new School())
            ->setName("School$postfix")
            ->setType(SchoolTypeEnum::Private)
            ->setArray(['test1' => 1, 'test2' => '2'])
            ->setInt(10)
            ->setBoolean(true)
            ->setDateTime(new DateTime('2024-01-01 10:00:00'))
            ->setDateTimeImmutable(new DateTimeImmutable('2024-01-01 11:00:00'))
            ->setFloat(35.575)
            ->setNullableType(null)
            ->setNumber(SchoolNumberIntEnum::Middle)
            ->setSchoolNonBacked(SchoolNonBackedEnum::Kindergarten);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernelBrowser = self::createClient();
        $container = $this->kernelBrowser->getContainer();

        /** @var DocumentManager $documentManager */
        $documentManager = $container->get(DocumentManager::class);
        $this->dm = $documentManager;

        $this->clear();
    }

    private function clear(): void
    {
        $application = new Application(self::bootKernel());

        try {
            $dropCommand = $application->find('aristek:dynamodb:drop-schema');
            $commandTester = new CommandTester($dropCommand);
            $commandTester->execute([]);
        } catch (Exception $e) {
            dump($e->getMessage());
        }

        try {
            $createCommand = $application->find('aristek:dynamodb:create-schema');
            $commandTester = new CommandTester($createCommand);
            $commandTester->execute([]);
        } catch (Exception $e) {
            dump($e->getMessage());
        }
    }
}
