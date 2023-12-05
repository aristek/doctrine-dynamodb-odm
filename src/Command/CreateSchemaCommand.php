<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Command;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbManager;
use Aws\DynamoDb\Exception\DynamoDbException;
use Exception;
use GuzzleHttp\Promise\Utils;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function in_array;
use function preg_quote;
use function sprintf;

#[AsCommand(
    name: 'aristek:dynamodb:create-schema',
    description: 'Processes the schema and create corresponding tables and indices.'
)]
final class CreateSchemaCommand extends AbstractSchemaCommand
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly int $ttl,
        private readonly string $baseTable,
        private readonly string $namespace,
        private readonly string $srcDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption(
                'skip-existing-table',
                null,
                InputOption::VALUE_NONE,
                "skip creating existing table!"
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                "output possible table creations without actually creating them."
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $skipExisting = $input->getOption('skip-existing-table');
        $dryRun = $input->getOption('dry-run');
        $dynamoManager = new DynamoDbManager($this->documentManager->getConfiguration()->getDynamodbConfig());

        $classes = $this->getManagedItemClasses($this->namespace, $this->srcDir);

        $tables = $waits = [];
        $tableName = $this->baseTable;

        foreach ($classes as $class) {
            try {
                $classMetadata = $this->documentManager->getClassMetadata($class);
            } catch (MappingException) {
                continue;
            }

            if ($classMetadata->isEmbeddedDocument || $classMetadata->isMappedSuperclass) {
                continue;
            }

            $primaryIndex = $classMetadata->getPrimaryIndex();

            if (!$primaryIndex) {
                throw new LogicException(sprintf('Primary index for class: "%s" not found.', $class));
            }

            $tables[$tableName]['primaryIndex'] = $this->getDynamodbIndex($primaryIndex);
            $tables[$tableName]['ttl'] = $this->ttl;
            $tables[$tableName]['lsis'] = $tables[$tableName]['lsis'] ?? [];
            $tables[$tableName]['gsis'] = $tables[$tableName]['gsis'] ?? [];
            $tables[$tableName]['lsisName'] = $tables[$tableName]['lsisName'] ?? [];
            $tables[$tableName]['gsisName'] = $tables[$tableName]['gsisName'] ?? [];

            foreach ($classMetadata->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
                if (!in_array($globalSecondaryIndex->name, $tables[$tableName]['gsisName'], true)) {
                    $tables[$tableName]['gsisName'][] = $globalSecondaryIndex->name;
                    $tables[$tableName]['gsis'][] = $this->getDynamodbIndex($globalSecondaryIndex);
                }
            }

            foreach ($classMetadata->getLocalSecondaryIndexes() as $localSecondaryIndex) {
                if (!in_array($localSecondaryIndex->name, $tables[$tableName]['lsisName'], true)) {
                    $tables[$tableName]['lsisName'][] = $localSecondaryIndex->name;
                    $tables[$tableName]['lsis'][] = $this->getDynamodbIndex($localSecondaryIndex);
                }
            }

            $output->writeln("Will create table <info>$tableName</info> for class <info>$class</info> ...");
        }

        foreach ($tables as $tableName => $table) {
            $listTables = $dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")));
            if ($listTables && !$skipExisting && !$dryRun) {
                throw new LogicException(sprintf('Table %s already exists!', $tableName));
            }

            if (!$dryRun) {
                try {
                    $dynamoManager->createTable(
                        $tableName,
                        $table['primaryIndex'],
                        $table['lsis'] ?? [],
                        $table['gsis'] ?? [],
                        ['ttl' => $table['ttl']]
                    );

                    if (isset($table['gsis'])) {
                        // if there is gsi, we need to wait before creating next table
                        $output->writeln("Will wait for GSI creation ...");
                        $dynamoManager->waitForTablesToBeFullyReady($tableName);
                    } else {
                        $waits[] = $dynamoManager->waitForTableCreation(
                            $tableName,
                            60,
                            1,
                            false
                        );
                    }

                    $output->writeln('Created.');
                } catch (DynamoDbException $e) {
                    if ("ResourceInUseException" === $e->getAwsErrorCode()) {
                        $output->writeln("<error>Table $tableName already exists!</error>");
                    } else {
                        throw $e;
                    }
                }
            }
        }

        if (!$dryRun) {
            $output->writeln("Waiting for all tables to be active ...");
            Utils::all($waits)->wait();
            $output->writeln("Done.");
        }

        return Command::SUCCESS;
    }
}
