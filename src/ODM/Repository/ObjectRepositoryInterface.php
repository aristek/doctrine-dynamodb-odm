<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\Id\Index;
use UnexpectedValueException;

/**
 * Contract for a Doctrine persistence layer ObjectRepository class to implement.
 *
 * @template-covariant T of object
 */
interface ObjectRepositoryInterface
{
    /**
     * Finds an object by its primary key / identifier.
     *
     * @param Index|null $id The identifier.
     *
     * @return object|null The object.
     * @psalm-return T|null
     */
    public function find(?Index $id);

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param Index                      $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null                   $limit
     * @param int|null                   $offset
     *
     * @return array<int, object> The objects.
     * @throws UnexpectedValueException
     */
    public function findBy(
        Index $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    );

    /**
     * Finds a single object by a set of criteria.
     *
     * @param Index $criteria The criteria.
     *
     * @return object|null The object.
     * @psalm-return T|null
     */
    public function findOneBy(Index $criteria);

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @psalm-return class-string<T>
     */
    public function getClassName(): string;
}
