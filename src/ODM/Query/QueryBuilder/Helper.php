<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder;

use Closure;
use function is_null;
use function reset;

final class Helper
{
    public static function arrayFirst($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return self::value($default);
            }

            return reset($array);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::value($default);
    }

    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
