<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use Aristek\Bundle\DynamodbBundle\ODM\Hydrator\HydratorException;
use Aristek\Bundle\DynamodbBundle\ODM\Iterator\UnmarshalIterator;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Index;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\ConditionAnalyzer\Analyzer;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\AwsWrappers\DynamoDbTable;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\DynamoDbManager;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\HasParsers;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDbClientService;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\EmptyAttributeFilter;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Exception\NotSupportedException;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Helper;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\RawDynamoDbQuery;
use ArrayIterator;
use Aws\DynamoDb\Marshaler;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Instantiator\Exception\ExceptionInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Illuminate\Support\Arr;
use LimitIterator;
use LogicException;
use ReflectionException;
use function array_keys;
use function array_map;
use function array_unique;
use function call_user_func;
use function collect;
use function compact;
use function count;
use function func_num_args;
use function is_array;
use function is_null;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;
use function sprintf;
use function strtolower;
use function with;

class QueryBuilder
{
    use HasParsers;

    public const HYDRATE_ARRAY = 2;
    public const HYDRATE_ITERATOR = 3;
    public const HYDRATE_OBJECT = 1;
    private const DEFAULT_TO_ITERATOR = true;
    private const MAX_LIMIT = -1;

    /**
     * The maximum number of records to return.
     */
    public ?int $limit = null;

    public array $wheres = [];

    protected DynamoDbManager $dbManager;

    protected ?Closure $decorator = null;

    /**
     * Specified index name for the query.
     */
    protected ?string $index = null;

    /**
     * When not using the iterator, you can store the lastEvaluatedKey to
     * paginate through the results. The getAll method will take this into account
     * when used with $use_iterator = false.
     */
    protected mixed $lastEvaluatedKey;

    private ClassMetadata $classMetadata;

    private DynamoDbTable $dbTable;

    private bool $sortOrderASC = true;

    /**
     * @throws ReflectionException
     * @throws MappingException
     */
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly string $className,
    ) {
        $this->dbManager = new DynamoDbManager(
            new DynamoDbClientService(
                $this->documentManager->getConfiguration()->getDynamoDbClient(),
                new Marshaler(['nullify_invalid' => true]),
                new EmptyAttributeFilter()
            )
        );

        $this->dbTable = new DynamoDbTable(
            $this->documentManager->getConfiguration()->getDynamodbConfig(),
            $this->documentManager->getConfiguration()->getDatabase()
        );

        $classMetadata = $this->documentManager->getClassMetadata($className);
        $this->classMetadata = $classMetadata;

        $this->setupExpressions($this->dbManager);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     */
    public function addNestedWhereQuery(QueryBuilder $query, string $boolean = 'and'): self
    {
        if (count($query->wheres)) {
            $type = 'Nested';
            $column = null;
            $value = $query->wheres;
            $this->wheres[] = compact('column', 'type', 'value', 'boolean');
        }

        return $this;
    }

    /**
     *   Examples:
     *
     *   For query such as
     *       $query = $model->where('count', 10)->limit(2);
     *       $last = $query->all()->last();
     *   Take the last item of this query result as the next "offset":
     *       $nextPage = $query->after($last)->limit(2)->all();
     *
     *   Alternatively, pass in nothing to reset the starting point.
     *
     * Determine the starting point (exclusively) of the query.
     * Unfortunately, offset of how many records to skip does not make sense for DynamoDb.
     * Instead, provide the last result of the previous query as the starting point for the next query.
     *
     * @throws ReflectionException
     */
    public function after(object $after = null): self
    {
        if (empty($after)) {
            $this->lastEvaluatedKey = null;

            return $this;
        }

        $afterKey = $this->classMetadata->getPrimaryIndexData($after);

        $analyzer = $this->getConditionAnalyzer();

        if ($index = $analyzer->index()) {
            foreach ($index->columns() as $column) {
                $afterKey[$column] = $this->classMetadata->getPropertyValue($after, $column);
            }
        }

        $this->lastEvaluatedKey = $this->dbManager->marshalItem($afterKey);

        return $this;
    }

    /**
     *   Examples:
     *
     *   For query such as
     *       $query = $model->where('count', 10)->limit(2);
     *       $items = $query->all();
     *   Take the last item of this query result as the next "offset":
     *       $nextPage = $query->afterKey($items->lastKey())->limit(2)->all();
     *
     *   Alternatively, pass in nothing to reset the starting point.
     *
     * Similar to after(), but instead of using the model instance, the model's keys are used.
     * Use $collection->lastKey() or $model->getKeys() to retrieve the value.
     *
     */
    public function afterKey(array $key = null): self
    {
        $this->lastEvaluatedKey = empty($key) ? null : $this->dbManager->marshalItem($key);

        return $this;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function all(
        array $columns = [],
        int $hydrationMode = self::HYDRATE_OBJECT
    ): array|Collection|UnmarshalIterator {
        $limit = $this->limit ?? static::MAX_LIMIT;

        return $this->getAll($columns, $limit, !isset($this->limit), hydrationMode: $hydrationMode);
    }

    /**
     * Implements the Query Chunk method
     *
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function chunk(int $chunkSize, callable $callback, int $hydrationMode = self::HYDRATE_OBJECT): bool
    {
        while (true) {
            $results = $this->getAll([], $chunkSize, false, $hydrationMode);

            if (!$results->isEmpty() && $callback($results) === false) {
                return false;
            }

            if (empty($this->lastEvaluatedKey)) {
                break;
            }
        }

        return true;
    }

    /**
     * @throws NotSupportedException
     */
    public function count(): int
    {
        $limit = $this->limit ?? static::MAX_LIMIT;
        $raw = $this->toDynamoDbQuery(['count(*)'], $limit);

        $res = $raw->op === 'Scan'
            ? $this->dbManager->client()->scan($raw->query)
            : $this->dbManager->client()->query($raw->query);

        return (int) $res['Count'];
    }

    public function decorate(Closure $closure): self
    {
        $this->decorator = $closure;

        return $this;
    }

    /**
     * @throws ReflectionException
     * @throws ExceptionInterface
     * @throws MappingException
     * @throws HydratorException
     */
    public function find(
        mixed $id,
        array $columns = [],
        int $hydrationMode = self::HYDRATE_OBJECT
    ): array|object|null {
        if ($this->isMultipleIds($id)) {
            return $this->findMany($id, $columns, $hydrationMode);
        }

        $this->resetExpressions();
        $primaryIndex = $this->classMetadata->getPrimaryIndex();

        if (!$primaryIndex) {
            throw new LogicException('Primary Index undefined.');
        }

        if (is_string($id) || is_numeric($id)) {
            $id = [$primaryIndex->hash => $id, $primaryIndex->range => $id];
        }

        $query = $this->dbManager
            ->table($this->dbTable->getTableName())
            ->setKey($this->dbManager->marshalItem($id))
            ->setConsistentRead(true);

        if (!empty($columns)) {
            $query
                ->setProjectionExpression($this->projectionExpression->parse($columns))
                ->setExpressionAttributeNames($this->expressionAttributeNames->all());
        }

        $item = $query->prepare($this->dbManager->client())->getItem();

        $item = Arr::get($item->toArray(), 'Item');

        if (empty($item)) {
            return null;
        }

        $data = $this->dbManager->unmarshalItem($item);
        $this->unmarshalIndexData($data);

        if ($hydrationMode === self::HYDRATE_OBJECT) {
            return $this->documentManager->getUnitOfWork()->getOrCreateDocument($this->className, $data);
        }

        return $data;
    }

    /**
     * @throws ExceptionInterface
     * @throws MappingException
     * @throws ReflectionException
     * @throws HydratorException
     */
    public function findMany(
        mixed $ids,
        array $columns = [],
        int $hydrationMode = self::HYDRATE_OBJECT
    ): array|Collection {
        $result = [];

        if ($hydrationMode === self::HYDRATE_OBJECT) {
            $result = new ArrayCollection();
        }

        if (empty($ids)) {
            return $result;
        }

        $this->resetExpressions();

        $table = $this->dbTable->getTableName();

        $keys = collect($ids)->map(function ($id) {
            if (!is_array($id)) {
                $id = [$this->classMetadata->getPrimaryIndex()->hash => $id];
            }

            return $this->dbManager->marshalItem($id);
        });

        $subQuery = $this->dbManager->newQuery()
            ->setKeys($keys->toArray())
            ->setProjectionExpression($this->projectionExpression->parse($columns))
            ->setExpressionAttributeNames($this->expressionAttributeNames->all())
            ->prepare($this->dbManager->client())
            ->query;

        $response = $this->dbManager->newQuery()
            ->setRequestItems([$table => $subQuery])
            ->prepare($this->dbManager->client())
            ->batchGetItem();

        foreach ($response['Responses'][$table] as $item) {
            $item = $this->dbManager->unmarshalItem($item);
            $this->unmarshalIndexData($item);

            if ($hydrationMode === self::HYDRATE_OBJECT) {
                $result->add($this->documentManager->getUnitOfWork()->getOrCreateDocument($this->className, $item));
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findOrFail(mixed $id, array $columns = [], int $hydrationMode = self::HYDRATE_OBJECT): array|object
    {
        $result = $this->find($id, $columns, $hydrationMode);

        if ($this->isMultipleIds($id)) {
            if ($hydrationMode === self::HYDRATE_OBJECT || count($result) === count(array_unique($id))) {
                return $result;
            }

            if ($hydrationMode === self::HYDRATE_ARRAY || count(array_keys($result)) === count(array_unique($id))) {
                return $result;
            }
        } else {
            if ($hydrationMode === self::HYDRATE_OBJECT && !is_null($result)) {
                return $result;
            }

            if ($hydrationMode === self::HYDRATE_ARRAY && !count($result)) {
                return $result;
            }
        }

        $this->throwNotFoundException();
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function first(array $columns = [], int $hydrationMode = self::HYDRATE_OBJECT): array|null|object
    {
        return $this->getAll($columns, 1, hydrationMode: $hydrationMode)->first() ?: null;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function firstOrFail(array $columns = [], int $hydrationMode = self::HYDRATE_OBJECT): array|object
    {
        $model = $this->first($columns, $hydrationMode);

        if ($hydrationMode === self::HYDRATE_OBJECT && !is_null($model)) {
            return $model;
        }

        if ($hydrationMode === self::HYDRATE_ARRAY && !count($model)) {
            return $model;
        }

        $this->throwNotFoundException();
    }

    /**
     * Create a new query instance for nested where condition.
     */
    public function forNestedWhere(): self
    {
        return $this->newQuery();
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    public function get(
        array $columns = [],
        int $hydrationMode = self::HYDRATE_OBJECT
    ): array|Collection|UnmarshalIterator {
        return $this->all($columns, $hydrationMode);
    }

    public function getDynamoDbManager(): DynamoDbManager
    {
        return $this->dbManager;
    }

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): self
    {
        $this->limit = $value;

        return $this;
    }

    /**
     * Get a new instance of the query builder.
     */
    public function newQuery(): self
    {
        return new self($this->documentManager, $this->className);
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @throws NotSupportedException
     */
    public function orWhere(string $column, string $operator = null, mixed $value = null): self
    {
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @throws NotSupportedException
     */
    public function orWhereIn(string $column, mixed $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'or');
    }

    /**
     * Add an "or where null" clause to the query.
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'or');
    }

    public function sortOrderASC(bool $sortOrderASC): self
    {
        $this->sortOrderASC = $sortOrderASC;

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): self
    {
        return $this->limit($value);
    }

    /**
     * @throws NotSupportedException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and'): self
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }

            return $this;
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        if (!$operator) {
            $operator = '=';
        }

        if (ComparisonOperator::isValidQueryDynamoDbOperator($operator, true)) {
            $operator = ComparisonOperator::getQueryDynamoDbOperator($operator);
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ($column instanceof Closure) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if (!ComparisonOperator::isValidOperator($operator)) {
            [$value, $operator] = [$value, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            throw new NotSupportedException('Closure in where clause is not supported');
        }

        $this->wheres[] = [
            'column'  => $column,
            'type'    => ComparisonOperator::getDynamoDbOperator($operator),
            'value'   => $value,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @throws NotSupportedException
     */
    public function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): self
    {
        if ($not) {
            throw new NotSupportedException('"not in" is not a valid DynamoDB comparison operator');
        }

        // If the value is a query builder instance, not supported
        if ($values instanceof static) {
            throw new NotSupportedException('Value is a query builder instance');
        }

        // If the value of the where in clause is actually a Closure, not supported
        if ($values instanceof Closure) {
            throw new NotSupportedException('Value is a Closure');
        }

        if (method_exists($values, 'toArray')) {
            $values = $values->toArray();
        }

        return $this->where($column, ComparisonOperator::IN, $values, $boolean);
    }

    /**
     * Add a nested where statement to the query.
     */
    public function whereNested(Closure $callback, string $boolean = 'and'): self
    {
        $callback($query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): self
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): self
    {
        $type = $not ? ComparisonOperator::NOT_NULL : ComparisonOperator::NULL;

        $this->wheres[] = compact('column', 'type', 'boolean');

        return $this;
    }

    /**
     * Set the index name manually
     */
    public function withIndex(string $index): self
    {
        $this->index = $index;

        return $this;
    }

    /**
     * @throws ExceptionInterface
     * @throws HydratorException
     * @throws MappingException
     * @throws NotSupportedException
     * @throws ReflectionException
     */
    private function getAll(
        array $columns = [],
        int $limit = QueryBuilder::MAX_LIMIT,
        bool $useIterator = QueryBuilder::DEFAULT_TO_ITERATOR,
        int $hydrationMode = self::HYDRATE_OBJECT,
    ): array|Collection|UnmarshalIterator {
        $analyzer = $this->getConditionAnalyzer();
        $result = [];

        if ($hydrationMode === self::HYDRATE_OBJECT) {
            $result = new ArrayCollection();
        }

        if (!$this->index && $analyzer->isExactSearch()) {
            $item = $this->find($analyzer->identifierConditionValues(), $columns, $hydrationMode);

            if (is_object($item)) {
                $result->add($item);
            } else {
                $result[] = $item;
            }

            return $result;
        }

        $raw = $this->toDynamoDbQuery($columns, $limit);

        if ($useIterator) {
            $iterator = $this->dbManager->client()->getIterator($raw->op, $raw->query);

            if (isset($raw->query['Limit'])) {
                $iterator = new LimitIterator($iterator, 0, $raw->query['Limit']);
            }
        } else {
            $res = $raw->op === 'Scan'
                ? $this->dbManager->client()->scan($raw->query)
                : $this->dbManager->client()->query($raw->query);

            $this->lastEvaluatedKey = Arr::get($res, 'LastEvaluatedKey');
            $iterator = $res['Items'];
        }

        if ($hydrationMode === self::HYDRATE_ITERATOR) {
            if (is_array($iterator) && !$useIterator) {
                $iterator = new ArrayIterator($iterator);
            }

            return new UnmarshalIterator($this->dbManager, $iterator);
        }

        foreach ($iterator as $item) {
            $item = $this->dbManager->unmarshalItem($item);
            $this->unmarshalIndexData($item);

            if ($hydrationMode === self::HYDRATE_OBJECT) {
                $model = $this->documentManager->getUnitOfWork()->getOrCreateDocument($this->className, $item);
                $result->add($model);
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function getConditionAnalyzer(): Analyzer
    {
        return with(new Analyzer())
            ->on($this->classMetadata)
            ->withIndex($this->index)
            ->analyze($this->wheres);
    }

    private function isMultipleIds($id): bool
    {
        $keys = collect($this->classMetadata->getIndexesNames());

        // could be ['id' => 'foo'], ['id1' => 'foo', 'id2' => 'bar']
        $single = $keys->first(fn($name) => !isset($id[$name])) === null;

        if ($single) {
            return false;
        }

        // could be ['foo', 'bar'], [['id1' => 'foo', 'id2' => 'bar'], ...]
        return $this->classMetadata->getGlobalSecondaryIndexes()
            ? is_array(Helper::arrayFirst($id))
            : is_array($id);
    }

    private function throwNotFoundException(): void
    {
        throw new LogicException(sprintf('Document "%s" not found.', $this->className));
    }

    /**
     * Return the raw DynamoDb query
     *
     * @throws NotSupportedException
     */
    private function toDynamoDbQuery(array $columns = [], int $limit = QueryBuilder::MAX_LIMIT): RawDynamoDbQuery
    {
        $this->resetExpressions();

        $op = 'Scan';
        $queryBuilder = $this->dbManager->table($this->dbTable->getTableName());

        if (!empty($this->wheres)) {
            $analyzer = $this->getConditionAnalyzer();

            if ($keyConditions = $analyzer->keyConditions()) {
                $op = 'Query';
                $queryBuilder->setKeyConditionExpression($this->keyConditionExpression->parse($keyConditions));
            }

            if ($filterConditions = $analyzer->filterConditions()) {
                $queryBuilder->setFilterExpression($this->filterExpression->parse($filterConditions));
            }

            if ($index = $analyzer->index()) {
                $queryBuilder->setIndexName($index->name);
            }
        }

        if ($this->index) {
            // If user specifies the index manually, respect that
            $queryBuilder->setIndexName($this->index);
        }

        if ($limit !== static::MAX_LIMIT) {
            $queryBuilder->setLimit($limit);
        }

        if (!empty($columns)) {
            // Either we try to get the count or specific columns
            if (array_map(static fn(string $item): string => strtolower($item), $columns) === ['count(*)']) {
                $queryBuilder->setSelect('COUNT');
            } else {
                $queryBuilder->setProjectionExpression($this->projectionExpression->parse($columns));
            }
        }

        if (!empty($this->lastEvaluatedKey)) {
            $queryBuilder->setExclusiveStartKey($this->lastEvaluatedKey);
        }

        $queryBuilder
            ->setExpressionAttributeNames($this->expressionAttributeNames->all())
            ->setExpressionAttributeValues($this->expressionAttributeValues->all());

        $raw = new RawDynamoDbQuery($op, $queryBuilder->prepare($this->dbManager->client())->query);

        if ($this->decorator) {
            call_user_func($this->decorator, $raw);
        }

        return $raw;
    }

    private function unmarshalIndexData(array &$data): void
    {
        $index = $this->classMetadata->getPrimaryIndex();

        $unmarshal = static function (array &$data, Index $index) {
            if (isset($data[$index->getHash()])) {
                $unmarshalValue = $index->strategy->getHashValue();
                $data[$index->getHash()] = $unmarshalValue ?: $data[$index->getHash()];
            }

            if ($index->getRange() && isset($data[$index->getRange()])) {
                $unmarshalValue = $index->strategy->getRangeValue();
                $data[$index->getRange()] = $unmarshalValue ?: $data[$index->getRange()];
            }
        };

        if ($index) {
            $unmarshal($data, $index);
        }

        foreach ($this->classMetadata->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
            $unmarshal($data, $globalSecondaryIndex);
        }

        foreach ($this->classMetadata->getLocalSecondaryIndexes() as $localSecondaryIndex) {
            $unmarshal($data, $localSecondaryIndex);
        }
    }
}
