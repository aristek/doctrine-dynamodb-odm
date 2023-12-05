<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\MappedSuperclass;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;

#[Document]
class Client extends Person
{
    #[Id]
    private ?string $id = null;

    #[Field]
    private string $phone;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }
}
