<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\ConditionAnalyzer;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\DynamoDb\ComparisonOperator;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Helper;
use Illuminate\Support\Arr;
use LogicException;
use function array_filter;
use function array_intersect;
use function array_values;
use function count;
use function in_array;

/**
 * Usage:
 *
 * $analyzer = with(new Analyzer)
 *  ->on($model)
 *  ->withIndex($index)
 *  ->analyze($conditions);
 *
 * $analyzer->isExactSearch();
 * $analyzer->keyConditions();
 * $analyzer->filterConditions();
 * $analyzer->index();
 */
final class Analyzer
{
    private ?ClassMetadata $classMetadata = null;

    private array $conditions = [];

    private ?string $indexName = null;

    public function analyze($conditions): self
    {
        $this->conditions = $conditions;

        return $this;
    }

    public function filterConditions(): array
    {
        $keyConditions = $this->keyConditions() ?: [];

        return array_filter($this->conditions, static function ($condition) use ($keyConditions) {
            return !in_array($condition, $keyConditions, true);
        });
    }

    public function identifierConditionValues(): array
    {
        $idConditions = $this->identifierConditions();

        if (!$idConditions) {
            return [];
        }

        $values = [];

        foreach ($idConditions as $condition) {
            $values[$condition['column']] = $condition['value'];
        }

        return $values;
    }

    public function identifierConditions(): ?array
    {
        $keyNames = $this->classMetadata->getIndexesNames();
        $conditions = $this->getConditions($keyNames);

        if (!$this->hasValidQueryOperator(...$keyNames)) {
            return null;
        }

        return $conditions;
    }

    public function index(): ?Index
    {
        return $this->getIndex();
    }

    public function isExactSearch(): bool
    {
        if (empty($this->conditions)) {
            return false;
        }

        if (empty($this->identifierConditions())) {
            return false;
        }

        foreach ($this->conditions as $condition) {
            if (Arr::get($condition, 'type') !== ComparisonOperator::EQ) {
                return false;
            }
        }

        return true;
    }

    public function keyConditions(): ?array
    {
        $index = $this->getIndex();

        if ($index) {
            return $this->getConditions($index->columns());
        }

        return $this->identifierConditions();
    }

    public function on(ClassMetadata $classMetadata): self
    {
        $this->classMetadata = $classMetadata;

        return $this;
    }

    public function withIndex(?string $index): self
    {
        $this->indexName = $index;

        return $this;
    }

    private function getCondition(string $column): ?array
    {
        return Helper::arrayFirst($this->conditions, static function ($condition) use ($column) {
            return $condition['column'] === $column;
        });
    }

    private function getConditions(array $columns): array
    {
        return array_filter($this->conditions, static function ($condition) use ($columns) {
            return in_array($condition['column'], $columns, true);
        });
    }

    private function getDynamoDbIndexKeys(): array
    {
        $keys = [];
        $primaryIndex = $this->classMetadata->getPrimaryIndex();

        if (!$primaryIndex) {
            throw new LogicException('Primary index undefined.');
        }

        $keys[$primaryIndex->name] = ['hash' => $primaryIndex->hash, 'range' => $primaryIndex->range];

        foreach ($this->classMetadata->getGlobalSecondaryIndexes() as $globalSecondaryIndex) {
            $keys[$globalSecondaryIndex->name] = [
                'hash'  => $globalSecondaryIndex->hash,
                'range' => $globalSecondaryIndex->range,
            ];
        }

        return $keys;
    }

    private function getIndex(): ?Index
    {
        if (empty($this->conditions)) {
            return null;
        }

        $index = null;

        foreach ($this->getDynamoDbIndexKeys() as $name => $keysInfo) {
            $conditionKeys = Arr::pluck($this->conditions, 'column');
            $keys = array_values($keysInfo);

            if (in_array($keysInfo['hash'], $conditionKeys, true)
                || count(array_intersect($conditionKeys, $keys)) === count($keys)
            ) {
                if (!isset($this->indexName) || $this->indexName === $name) {
                    $rangeRequested = in_array($keysInfo['range'], $conditionKeys, true);
                    $index = new Index(
                        $name,
                        Arr::get($keysInfo, 'hash'),
                        $rangeRequested ? Arr::get($keysInfo, 'range') : ''
                    );

                    break;
                }
            }
        }

        if ($index && !$this->hasValidQueryOperator($index->hash, $index->range)) {
            $index = null;
        }

        return $index;
    }

    private function hasValidQueryOperator(string $hash, string $range = null): bool
    {
        $hashConditionType = $this->getCondition($hash)['type'] ?? '';
        $validQueryOp = ComparisonOperator::isValidQueryDynamoDbOperator($hashConditionType);

        if ($validQueryOp && $range) {
            $rangeConditionType = $this->getCondition($range)['type'] ?? '';
            $validQueryOp = ComparisonOperator::isValidQueryDynamoDbOperator(
                $rangeConditionType,
                true
            );
        }

        return $validQueryOp;
    }
}
