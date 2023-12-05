<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Id;

use Aristek\Bundle\DynamodbBundle\ODM\DocumentManager;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use function sprintf;

final class UuidGenerator implements IdGenerator
{
    public const STRATEGY = 'uuid';
    public const UUID_1 = 1;
    public const UUID_4 = 4;
    public const UUID_6 = 6;
    public const UUID_7 = 7;

    public static function validateVersion(int $version): void
    {
        if ($version === self::UUID_1
            || $version === self::UUID_4
            || $version === self::UUID_6
            || $version === self::UUID_7
        ) {
            return;
        }

        throw new InvalidArgumentException(sprintf('UUID version "%s" not supported.', $version));
    }

    /**
     * Generates a new UUID
     */
    public function generate(DocumentManager $dm, object $document): string
    {
        return match ($dm->getConfiguration()->getUuidVersion()) {
            self::UUID_1 => (string) Uuid::v1(),
            self::UUID_4 => (string) Uuid::v4(),
            self::UUID_6 => (string) Uuid::v6(),
            self::UUID_7 => (string) Uuid::v7(),
            default => throw new InvalidArgumentException("UUID version required configuration.")
        };
    }
}
