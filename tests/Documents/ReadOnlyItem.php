<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;

#[Document(readOnly: true)]
final class ReadOnlyItem
{
    #[Id]
    private ?string $id = null;

    #[Field]
    private string $name;

    public function getId(): ?string
    {
        return $this->id;
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
}
