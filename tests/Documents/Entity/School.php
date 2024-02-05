<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Pk;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceOne;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolNonBackedEnum;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolNumberIntEnum;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Enum\SchoolTypeEnum;
use DateTime;
use DateTimeImmutable;

#[Document]
class School
{
    #[Field]
    private array $array;

    #[Field]
    private bool $boolean;

    #[Field]
    private DateTime $dateTime;

    #[Field]
    private DateTimeImmutable $dateTimeImmutable;

    #[ReferenceOne(targetDocument: District::class, cascade: 'all')]
    private District $district;

    #[Field]
    private float $float;

    #[Pk]
    private ?string $id = null;

    #[Field]
    private int $int;

    #[Field]
    private string $name;

    #[Field(type: 'string', nullable: true, enumType: SchoolTypeEnum::class)]
    private ?SchoolTypeEnum $nullableType;

    #[Field]
    private ?SchoolNumberIntEnum $number = null;

    private ?SchoolNonBackedEnum $schoolNonBacked = null;

    #[Field]
    private SchoolTypeEnum $type;

    public function getArray(): array
    {
        return $this->array;
    }

    public function setArray(array $array): self
    {
        $this->array = $array;

        return $this;
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(DateTime $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function getDateTimeImmutable(): DateTimeImmutable
    {
        return $this->dateTimeImmutable;
    }

    public function setDateTimeImmutable(DateTimeImmutable $dateTimeImmutable): self
    {
        $this->dateTimeImmutable = $dateTimeImmutable;

        return $this;
    }

    public function getDistrict(): District
    {
        return $this->district;
    }

    public function setDistrict(District $district): self
    {
        $this->district = $district;

        return $this;
    }

    public function getFloat(): float
    {
        return $this->float;
    }

    public function setFloat(float $float): self
    {
        $this->float = $float;

        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getInt(): int
    {
        return $this->int;
    }

    public function setInt(int $int): self
    {
        $this->int = $int;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getNullableType(): ?SchoolTypeEnum
    {
        return $this->nullableType;
    }

    public function setNullableType(?SchoolTypeEnum $nullableType): self
    {
        $this->nullableType = $nullableType;

        return $this;
    }

    public function getNumber(): ?SchoolNumberIntEnum
    {
        return $this->number;
    }

    public function setNumber(?SchoolNumberIntEnum $number): self
    {
        $this->number = $number;

        return $this;
    }

    public function getSchoolNonBacked(): ?SchoolNonBackedEnum
    {
        return $this->schoolNonBacked;
    }

    public function setSchoolNonBacked(?SchoolNonBackedEnum $schoolNonBacked): self
    {
        $this->schoolNonBacked = $schoolNonBacked;

        return $this;
    }

    public function getType(): SchoolTypeEnum
    {
        return $this->type;
    }

    public function setType(SchoolTypeEnum $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isBoolean(): bool
    {
        return $this->boolean;
    }

    public function setBoolean(bool $boolean): self
    {
        $this->boolean = $boolean;

        return $this;
    }
}
