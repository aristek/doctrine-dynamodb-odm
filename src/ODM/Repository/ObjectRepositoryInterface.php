<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Repository;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
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
     * @param PrimaryKey|null $id The identifier.
     *
     * @return object|null The object.
     * @psalm-return T|null
     */
    public function find(?PrimaryKey $id);

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param PrimaryKey                 $criteria
     * @param array<string, string>|null $orderBy
     * @param int|null                   $limit
     * @param int|null                   $offset
     *
     * @return array<int, object> The objects.
     * @throws UnexpectedValueException
     */
    public function findBy(
        PrimaryKey $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    );

    /**
     * Finds a single object by a set of criteria.
     *
     * @param PrimaryKey $criteria The criteria.
     *
     * @return object|null The object.
     * @psalm-return T|null
     */
    public function findOneBy(PrimaryKey $criteria);

    /**
     * Returns the class name of the object managed by the repository.
     *
     * @psalm-return class-string<T>
     */
    public function getClassName(): string;
}
