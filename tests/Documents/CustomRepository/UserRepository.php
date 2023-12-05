<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository;

use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\Iterator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Exception\NotSupportedException;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\DocumentRepository;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;

final class UserRepository extends DocumentRepository
{
    /**
     * @throws NotSupportedException
     * @throws ExceptionInterface
     * @throws MappingException
     * @throws ReflectionException
     * @throws HydratorException
     */
    public function getAdminUsers(Game $game): Iterator
    {
        return $this
            ->createQueryBuilder()
            ->where('pk', 'User')
            ->where('sk', ComparisonOperator::BEGINS_WITH, 'admin')
            ->all(hydrationMode: QueryBuilder::HYDRATE_ITERATOR);
    }
}
