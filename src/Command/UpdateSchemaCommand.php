<?php
declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Command;

use Exception;
use GuzzleHttp\Promise\Utils;
use LogicException;
use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbIndex;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbManager;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbTable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_keys;
use function implode;
use function preg_quote;
use function sprintf;

#[AsCommand(
    name: 'aristek:dynamodb:update-schema',
    description: 'Update the dynamodb tables.'
)]
final class UpdateSchemaCommand extends AbstractSchemaCommand
{
    public function __construct(
        private readonly DocumentManager $documentManager,
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
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                "dry run: prints out changes without really updating schema"
            );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption('dry-run');
        $dynamoManager = new DynamoDbManager($this->documentManager->getConfiguration()->getDynamodbConfig());
        $classes = $this->getManagedItemClasses($this->namespace, $this->srcDir);

        $classCreation = [];
        $gsiChanges = [];
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

            if (!$dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                // will create
                $currentClass = $this;
                $classCreation[] = static function () use (
                    $classMetadata,
                    $isDryRun,
                    $output,
                    $class,
                    $dynamoManager,
                    $tableName,
                    $currentClass
                ) {
                    $primaryIndex = $classMetadata->getPrimaryIndex();
                    if (!$primaryIndex) {
                        throw new LogicException('Primary Index undefined.');
                    }

                    $gsis = [];
                    foreach ($classMetadata->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
                        $gsis[] = $currentClass->getDynamodbIndex($globalSecondaryIndex);
                    }

                    $lsis = [];
                    foreach ($classMetadata->getLocalSecondaryIndexes() as $localSecondaryIndex) {
                        $lsis[] = $currentClass->getDynamodbIndex($localSecondaryIndex);
                    }

                    $output->writeln("Will create table <info>$tableName</info> for class <info>$class</info> ...");

                    if (!$isDryRun) {
                        $tags = [];

                        $dynamoManager->createTable(
                            $tableName,
                            $currentClass->getDynamodbIndex($primaryIndex),
                            $lsis,
                            $gsis,
                            $tags
                        );

                        $output->writeln('Created.');
                    }

                    return $tableName;
                };
            } else {
                // will update
                $table = new DynamoDbTable($this->documentManager->getConfiguration()->getDynamodbConfig(), $tableName);

                $oldPrimaryIndex = $table->getPrimaryIndex();
                $primaryIndex = $classMetadata->getPrimaryIndex();

                if (!$oldPrimaryIndex->equals($this->getDynamodbIndex($primaryIndex))) {
                    throw new LogicException(
                        sprintf(
                            "Primary index changed, which is not possible when table is already created! [Table = %s]",
                            $tableName
                        )
                    );
                }

                $oldLsis = $table->getLocalSecondaryIndices();
                foreach ($classMetadata->getLocalSecondaryIndexes() as $localSecondaryIndex) {
                    $idx = $this->getDynamodbIndex($localSecondaryIndex);

                    if (!isset($oldLsis[$idx->getName()])) {
                        throw new LogicException(
                            sprintf(
                                "LSI named %s did not exist, you cannot update LSI when table is created! [Table = %s]",
                                $idx->getName(),
                                $tableName
                            )
                        );
                    }

                    unset($oldLsis[$idx->getName()]);
                }

                if ($oldLsis) {
                    throw new LogicException(
                        sprintf(
                            "LSI named %s removed, you cannot remove any LSI when table is created!",
                            implode(",", array_keys($oldLsis))
                        )
                    );
                }

                $provisionedBilling = true;

                $oldGsis = $table->getGlobalSecondaryIndices();

                foreach ($classMetadata->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
                    $idx = $this->getDynamodbIndex($globalSecondaryIndex);

                    if (!isset($oldGsis[$idx->getName()])) {
                        // new GSI
                        $gsiChanges[] = static function () use (
                            $isDryRun,
                            $dynamoManager,
                            $output,
                            $class,
                            $tableName,
                            $table,
                            $idx,
                            $provisionedBilling
                        ) {
                            $output->writeln(
                                "Will add GSI ["
                                .$idx->getName()
                                ."] to table <info>$tableName</info> for class <info>$class</info> ..."
                            );

                            if (!$isDryRun) {
                                $table->addGlobalSecondaryIndex($idx, $provisionedBilling);
                                // if there is gsi alteration, we need to wait before continue
                                $output->writeln('Will wait for creation of GSI '.$idx->getName().' ...');
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $output->writeln('Done.');
                            }

                            return $tableName;
                        };
                    } else {
                        if (!$idx->equals($oldGsis[$idx->getName()])) {
                            $gsiChanges[] = static function () use (
                                $isDryRun,
                                $dynamoManager,
                                $output,
                                $class,
                                $tableName,
                                $table,
                                $idx,
                                $provisionedBilling
                            ) {
                                $output->writeln(
                                    "Will update GSI ["
                                    .$idx->getName()
                                    ."] on table <info>$tableName</info> for class <info>$class</info> ..."
                                );

                                if (!$isDryRun) {
                                    // if there is gsi alteration, we need to wait before continue
                                    $table->deleteGlobalSecondaryIndex($idx->getName());
                                    $output->writeln('Will wait for deletion of GSI '.$idx->getName().' ...');
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    $table->addGlobalSecondaryIndex($idx, $provisionedBilling);
                                    $output->writeln('Will wait for creation of GSI '.$idx->getName().' ...');
                                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                    $output->writeln('Done.');
                                }

                                return $tableName;
                            };
                        }

                        unset($oldGsis[$idx->getName()]);
                    }
                }

                if ($oldGsis) {
                    /** @var DynamoDbIndex $removedGsi */
                    foreach ($oldGsis as $removedGsi) {
                        $gsiChanges[] = static function () use (
                            $isDryRun,
                            $dynamoManager,
                            $output,
                            $class,
                            $tableName,
                            $table,
                            $removedGsi
                        ) {
                            $output->writeln(
                                "Will remove GSI [".$removedGsi->getName()."] from table
                                            <info>$tableName</info> for class <info>$class</info> ..."
                            );

                            if (!$isDryRun) {
                                $table->deleteGlobalSecondaryIndex($removedGsi->getName());
                                $output->writeln("Will wait for deletion of GSI ".$removedGsi->getName()." ...");
                                $dynamoManager->waitForTablesToBeFullyReady($tableName, 300, 5);
                                $output->writeln('Done.');
                            }

                            return $tableName;
                        };
                    }
                }
            }
        }

        if (!$classCreation && !$gsiChanges) {
            $output->writeln('Nothing to change.');
        } else {
            $waits = [];
            foreach ($classCreation as $callable) {
                $tableName = $callable();

                if (!$isDryRun) {
                    $waits[] = $dynamoManager->waitForTableCreation($tableName, 60, 1, false);
                }
            }

            if ($waits) {
                $output->writeln('Waiting for all created tables to be active ...');
                Utils::all($waits)->wait();
                $output->writeln('Done.');
            }

            foreach ($gsiChanges as $callable) {
                $callable();
            }
        }

        return Command::SUCCESS;
    }
}
