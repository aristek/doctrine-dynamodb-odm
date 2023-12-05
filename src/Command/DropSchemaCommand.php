<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Command;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbManager;
use GuzzleHttp\Promise\Utils;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'aristek:dynamodb:drop-schema',
    description: 'Drop the dynamodb tables.'
)]
final class DropSchemaCommand extends AbstractSchemaCommand
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly string $baseTable,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $waits = [];
        $dynamoManager = new DynamoDbManager($this->documentManager->getConfiguration()->getDynamodbConfig());
        $dynamoManager->deleteTable($this->baseTable);

        $waits[] = $dynamoManager->waitForTableDeletion(
            $this->baseTable,
            60,
            1,
            false
        );

        $output->writeln('Waiting for all tables to be inactive.');
        Utils::all($waits)->wait();
        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}
