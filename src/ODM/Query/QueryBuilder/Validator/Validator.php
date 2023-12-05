<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Validator;

use BackedEnum;
use LogicException;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\ClassMetadata;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\MappingException;
use Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Validator\Constraints\IsEnum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;
use function count;
use function sprintf;

final class Validator
{
    private const LIST_ITEM_VALIDATION_MESSAGE = 'Value for type "list" should be of type {{ type }} or enum.';

    public function __construct(
        private readonly ClassMetadata $classMetadata,
    ) {
    }

    /**
     * @throws MappingException
     */
    public function validateAttributes(array $attributes): array
    {
        foreach ($attributes as $property => $attribute) {
            $fieldDefinition = $this->classMetadata->getFieldMapping($property);

            if (!$fieldDefinition || !$fieldDefinition['type']) {
                continue;
            }

            $this->validateDehydrateValue($fieldDefinition['type'], $attribute);
        }

        return $attributes;
    }

    public function validateDehydrateValue(string $type, mixed $value): void
    {
        $validator = Validation::createValidator();

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $errors = $validator->validate($value, $this->getDehydrateValidationConstraint($type));

        if (count($errors)) {
            throw new ValidationFailedException($value, $errors);
        }
    }

    private function getDehydrateValidationConstraint(string $type): Constraint
    {
        return match ($type) {
            'string', 'bool' => new Type(type: $type),
            'number' => new Type(type: 'numeric'),
            'map' => new Type(type: 'iterable'),
            'list' => new Sequentially([
                new Type(type: 'iterable'),
                new All([
                    new AtLeastOneOf([
                        new Type(type: 'scalar', message: self::LIST_ITEM_VALIDATION_MESSAGE),
                        new IsEnum(),
                    ]),
                ]),
            ]),
            default => throw new LogicException(sprintf('Type "%s" not supported.', $type))
        };
    }
}
