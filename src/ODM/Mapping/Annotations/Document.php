<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbManager;
use Attribute;
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * Identifies a class as a document that can be stored in the database
 *
 * @Annotation
 * @NamedArgumentConstructor
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Document extends AbstractDocument
{
    /**
     * @Enum(value={"PROVISIONED", "PAY_PER_REQUEST"})
     */
    public string $billingType = DynamoDbManager::PROVISIONED;

    /**
     * @var PrimaryKey[]|array
     */
    public array $globalSecondaryIndexes = [];

    public IndexStrategy $indexStrategy;

    /**
     * @var PrimaryKey[]|array
     */
    public array $localSecondaryIndexes = [];

    public function __construct(
        ?IndexStrategy $indexStrategy = null,
        public readonly ?string $db = null,
        public readonly ?string $repositoryClass = null,
        public readonly bool $readOnly = false,
        string $billingType = DynamoDbManager::PROVISIONED,
        array $globalSecondaryIndexes = [],
        array $localSecondaryIndexes = [],
        public readonly ?int $ttlAttribute = null,
    ) {
        $this->billingType = $billingType;

        $this->indexStrategy = $indexStrategy ?: new IndexStrategy(range: IndexStrategy::SK_STRATEGY_FORMAT);

        foreach ($globalSecondaryIndexes as $globalSecondaryIndex) {
            if (!$globalSecondaryIndex instanceof PrimaryKey) {
                MappingException::invalidDocumentIndex('globalSecondaryIndex');
            }

            $this->globalSecondaryIndexes[] = $globalSecondaryIndex;
        }

        foreach ($localSecondaryIndexes as $localSecondaryIndex) {
            if (!$localSecondaryIndex instanceof PrimaryKey) {
                MappingException::invalidDocumentIndex('localSecondaryIndex');
            }

            $this->localSecondaryIndexes[] = $localSecondaryIndex;
        }
    }
}
