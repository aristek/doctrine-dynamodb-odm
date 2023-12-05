<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Query\QueryBuilder\Validator\Constraints;

use BackedEnum;
use MyCLabs\Enum\Enum;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use function is_subclass_of;

final class IsEnumValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof IsEnum) {
            throw new UnexpectedTypeException($constraint, IsEnum::class);
        }

        if ($value instanceof BackedEnum || is_subclass_of($value, Enum::class)) {
            return;
        }

        $this->context
            ->buildViolation('Value not enum.')
            ->setCode('Value not enum.')
            ->addViolation();
    }
}
