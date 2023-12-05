<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference\WithMapping;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceMany;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Document]
final class Organization
{
    #[ReferenceMany(targetDocument: Affiliate::class, cascade: 'all', mappedBy: 'organization')]
    private Collection $affiliates;

    #[Id]
    private ?string $id = null;

    #[Field]
    private string $name;

    public function __construct()
    {
        $this->affiliates = new ArrayCollection();
    }

    public function addAffiliate(Affiliate $affiliate): self
    {
        if (!$this->affiliates->contains($affiliate)) {
            $this->affiliates->add($affiliate);
            $affiliate->setOrganization($this);
        }

        return $this;
    }

    public function getAffiliates(): Collection
    {
        return $this->affiliates;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function removeAffiliate(Affiliate $affiliate): self
    {
        $this->affiliates->removeElement($affiliate);

        return $this;
    }
}
