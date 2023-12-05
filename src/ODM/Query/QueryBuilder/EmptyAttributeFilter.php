<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;

use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function trim;

final class EmptyAttributeFilter
{
    /**
     * Set empty values to NULL since DynamoDB does not like empty values.
     */
    public function filter(&$store): void
    {
        foreach ($store as $key => &$value) {
            $value = is_string($value) ? trim($value) : $value;
            $empty = $value === null || (is_array($value) && empty($value));

            $empty = $empty || (is_scalar($value) && $value !== false && (string) $value === '');

            if ($empty) {
                $store[$key] = null;
            } else {
                if (is_object($value)) {
                    $value = (array) $value;
                }

                if (is_array($value)) {
                    $this->filter($value);
                }
            }
        }
    }
}
