<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Annotation;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\AlsoLoad;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;

#[Document]
class Product
{
    #[AlsoLoad('secondAlias')]
    #[Field]
    private ?string $alias = null;

    #[Id]
    private ?string $id = null;

    #[AlsoLoad(['alias', 'secondAlias'])]
    #[Field]
    private ?string $name = null;

    private string $realName;

    #[Field]
    private ?string $secondAlias = null;

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(?string $alias): self
    {
        $this->alias = $alias;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getRealName(): string
    {
        return $this->realName;
    }

    public function getSecondAlias(): ?string
    {
        return $this->secondAlias;
    }

    public function setSecondAlias(?string $secondAlias): self
    {
        $this->secondAlias = $secondAlias;

        return $this;
    }

    #[AlsoLoad(['alias', 'secondAlias'])]
    public function populateRealName(string $name): void
    {
        $this->realName = $name;
    }
}
