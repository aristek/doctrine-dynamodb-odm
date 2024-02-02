<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\Iterator;
use Aristek\Bundle\DynamodbBundle\ODM\ManagerRegistry;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Exception\NotSupportedException;
use Aristek\Bundle\DynamodbBundle\ODM\Repository\ServiceDocumentRepository;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\Game;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity\User;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\MappingException;
use ReflectionException;

final class UserRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

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
            ->where('pk', ComparisonOperator::BEGINS_WITH, 'U#')
            ->where('sk', ComparisonOperator::BEGINS_WITH, 'admin')
            ->all(hydrationMode: QueryBuilder::HYDRATE_ITERATOR);
    }
}
