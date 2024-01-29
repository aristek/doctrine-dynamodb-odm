<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Test\Helper;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Exception;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use function dump;

trait FixturesTrait
{
    protected function clear(): void
    {
        $application = new Application(self::bootKernel());

        try {
            $dropCommand = $application->find('aristek:dynamodb:drop-schema');
            $commandTester = new CommandTester($dropCommand);
            $commandTester->execute([]);
        } catch (Exception) {
        }

        try {
            $createCommand = $application->find('aristek:dynamodb:create-schema');
            $commandTester = new CommandTester($createCommand);
            $commandTester->execute([]);
        } catch (Exception $e) {
            dump($e);
        }
    }

    protected function loadDynamoFixtures(array $fixtures, bool $clearTable = true): void
    {
        /** @var DocumentManager $documentManager */
        $documentManager = self::getContainer()->get(DocumentManager::class);

        if ($clearTable) {
            $this->clear();
        }

        foreach ($fixtures as $class => $data) {
            foreach ($data as $name => $entity) {
                $documentManager->persist($entity);
                $documentManager->flush();
                $this->fixtures[$name] = $entity;
            }
        }
    }
}
