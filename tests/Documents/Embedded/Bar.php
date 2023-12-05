<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Embedded;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\EmbedMany;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\EmbedOne;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;

#[Document]
class Bar
{
    #[Id]
    private ?string $id = null;

    /**
     * @var Collection<int, Location>
     */
    #[EmbedMany(targetDocument: Location::class)]
    private Collection $locations;

    #[Field]
    private ?string $name;

    #[EmbedOne(nullable: true, targetDocument: Location::class)]
    private ?Location $singleLocation = null;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
        $this->locations = new ArrayCollection();
    }

    public function addLocation(Location $location): self
    {
        if (!$this->locations->contains($location)) {
            $this->locations->add($location);
        }

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

    /**
     * @return Collection<int, Location>
     */
    public function getLocations(): Collection
    {
        return $this->locations;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSingleLocation(): ?Location
    {
        return $this->singleLocation;
    }

    public function setSingleLocation(?Location $singleLocation): self
    {
        $this->singleLocation = $singleLocation;

        return $this;
    }

    public function removeLocation(Location $location): self
    {
        $this->locations->removeElement($location);

        return $this;
    }
}
