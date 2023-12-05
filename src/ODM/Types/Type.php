<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Types;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Types;
use InvalidArgumentException;
use function end;
use function explode;
use function sprintf;
use function str_replace;

abstract class Type
{
    public const BOOL = 'bool';
    public const COLLECTION = 'collection';
    public const DATE = 'date';
    public const DATE_IMMUTABLE = 'date_immutable';
    public const FLOAT = 'float';
    public const HASH = 'hash';
    public const INT = 'int';
    public const KEY = 'key';
    public const RAW = 'raw';
    public const STRING = 'string';

    /**
     * @var Type[] Map of already instantiated type objects. One instance per type (flyweight).
     */
    private static array $typeObjects = [];

    /**
     * @var string[] The map of supported doctrine mapping types.
     */
    private static array $typesMap = [
        self::BOOL           => Types\BooleanType::class,
        self::DATE           => Types\DateType::class,
        self::DATE_IMMUTABLE => Types\DateImmutableType::class,
        self::INT            => Types\IntType::class,
        self::FLOAT          => Types\FloatType::class,
        self::STRING         => Types\StringType::class,
        self::HASH           => Types\HashType::class,
        self::COLLECTION     => Types\CollectionType::class,
        self::RAW            => Types\RawType::class,
    ];

    /**
     * Adds a custom type to the type map.
     *
     * @throws MappingException
     */
    public static function addType(string $name, string $className): void
    {
        if (isset(self::$typesMap[$name])) {
            throw MappingException::typeExists($name);
        }

        self::$typesMap[$name] = $className;
    }

    public static function convertPHPToDatabaseValue(mixed $value): mixed
    {
        $type = self::getTypeFromPHPVariable($value);

        if ($type !== null) {
            return $type->convertToDatabaseValue($value);
        }

        return $value;
    }

    /**
     * Get a Type instance.
     *
     * @throws InvalidArgumentException
     */
    public static function getType(string $type): Type
    {
        if (!isset(self::$typesMap[$type])) {
            throw new InvalidArgumentException(sprintf('Invalid type specified "%s".', $type));
        }

        if (!isset(self::$typeObjects[$type])) {
            $className = self::$typesMap[$type];
            self::$typeObjects[$type] = new $className();
        }

        return self::$typeObjects[$type];
    }

    /**
     * Get a Type instance based on the type of the passed php variable.
     *
     * @throws InvalidArgumentException
     */
    public static function getTypeFromPHPVariable(mixed $variable): ?Type
    {
        if (is_int($variable)) {
            return self::getType('int');
        }

        return null;
    }

    /**
     * Get the types array map which holds all registered types and the corresponding type class
     */
    public static function getTypesMap(): array
    {
        return self::$typesMap;
    }

    /**
     * Checks if exists support for a type.
     */
    public static function hasType(string $name): bool
    {
        return isset(self::$typesMap[$name]);
    }

    /**
     * Overrides an already defined type to use a different implementation.
     *
     * @throws MappingException
     */
    public static function overrideType(string $name, string $className): void
    {
        if (!isset(self::$typesMap[$name])) {
            throw MappingException::typeNotFound($name);
        }

        self::$typesMap[$name] = $className;
    }

    /**
     * Register a new type in the type map.
     */
    public static function registerType(string $name, string $class): void
    {
        self::$typesMap[$name] = $class;
    }

    public function __toString(): string
    {
        $e = explode('\\', static::class);
        $className = end($e);

        return str_replace('Type', '', $className);
    }

    public function closureToPHP(): string
    {
        return '$return = $value;';
    }

    /**
     * Converts a value from its PHP representation to its database representation of this type.
     */
    public function convertToDatabaseValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Converts a value from its database representation to its PHP representation of this type.
     */
    public function convertToPHPValue(mixed $value): mixed
    {
        return $value;
    }

    /**
     * Prevent instantiation and force use of the factory method.
     */
    final private function __construct()
    {
    }
}
