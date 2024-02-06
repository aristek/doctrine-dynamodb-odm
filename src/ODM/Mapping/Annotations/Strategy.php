<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use BackedEnum;
use ReflectionClass;
use function array_map;
use function implode;
use function is_string;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use const PREG_OFFSET_CAPTURE;

final class Strategy
{
    public const PK_STRATEGY_FORMAT = '{CLASS_SHORT_NAME}#{id}';
    public const SK_STRATEGY_FORMAT = '{CLASS}';

    public function __construct(
        public readonly string $mask,
    ) {
    }

    public function marshal(object|string $document, array $attributes = []): string
    {
        $ref = new ReflectionClass($document);

        return preg_replace_callback(
            '/(\{\w+\})/',
            static function ($matches) use ($ref, $document, $attributes) {
                foreach ($matches as $match) {
                    preg_match('/\{(\w+)\}/', $match, $matches);
                    $key = $matches[1] ?? null;
                    if (!$key) {
                        continue;
                    }

                    switch ($key) {
                        case 'CLASS':
                            return $ref->getShortName();
                        case 'CLASS_SHORT_NAME':
                            preg_match_all('/[A-Z]/', $ref->getShortName(), $matches, PREG_OFFSET_CAPTURE);
                            $keys = array_map(static fn(array $item): string => $item[0], $matches[0]);

                            return implode('', $keys);
                        default:
                            if (is_string($document)) {
                                return $attributes[$key] ?? '';
                            }

                            if ($ref->hasProperty($key)) {
                                $value = $ref->getProperty($key)->getValue($document);

                                if ($value instanceof BackedEnum) {
                                    $value = $value->value;
                                }

                                return $value;
                            }
                    }
                }

                return '';
            },
            $this->mask
        );
    }
}
