<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations;

use ReflectionClass;
use ReflectionException;
use function array_map;
use function implode;
use function is_string;
use function preg_replace_callback;

final class IndexStrategy
{
    public const PK_STRATEGY_FORMAT = '{CLASS_SHORT_NAME}#{id}';
    public const SK_STRATEGY_FORMAT = '{CLASS}';

    private ?string $hashValue = null;

    private ?string $rangeValue = null;

    public function __construct(
        public readonly string $hash = self::PK_STRATEGY_FORMAT,
        public readonly ?string $range = null,
    ) {
    }

    /**
     * @throws ReflectionException
     */
    public function getHash(object|string $document, array $attributes = []): string
    {
        return $this->format($this->hash, $document, $attributes);
    }

    public function getHashValue(): ?string
    {
        return $this->hashValue;
    }

    /**
     * @throws ReflectionException
     */
    public function getRange(object|string $document, array $attributes = []): ?string
    {
        return $this->range ? $this->format($this->range, $document, $attributes) : null;
    }

    public function getRangeValue(): ?string
    {
        return $this->rangeValue;
    }

    /**
     * @throws ReflectionException
     */
    private function format(string $index, object|string $document, array $attributes): string
    {
        $ref = new ReflectionClass($document);

        return preg_replace_callback(
            '/(\{\w+\})/',
            function ($matches) use ($ref, $document, $attributes, $index) {
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
                                if (!isset($attributes[$key])) {
                                    return '';
                                }

                                $value = $attributes[$key];

                                $index === self::PK_STRATEGY_FORMAT
                                    ? $this->hashValue = $value
                                    : $this->rangeValue = $value;

                                return $value;
                            }

                            if ($ref->hasProperty($key)) {
                                $value = $ref->getProperty($key)->getValue($document);

                                $index === self::PK_STRATEGY_FORMAT
                                    ? $this->hashValue = $value
                                    : $this->rangeValue = $value;

                                return $value;
                            }
                    }
                }

                return '';
            },
            $index
        );
    }
}
