<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\EmbeddedDocument;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\EmbedOne;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceMany;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceOne;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[EmbeddedDocument]
class Location
{
    /**
     * @var Collection<Coordinate>
     */
    #[ReferenceMany(targetDocument: Coordinate::class, cascade: 'all')]
    private Collection $coordinates;

    #[ReferenceOne(targetDocument: Coordinate::class, cascade: 'all')]
    private ?Coordinate $currentCoordination = null;

    #[Field]
    private ?string $name;

    #[EmbedOne(nullable: true, targetDocument: Location::class)]
    private ?Location $parent = null;

    public function __construct(?string $name = null)
    {
        $this->coordinates = new ArrayCollection();
        $this->name = $name;
    }

    public function addCoordinate(Coordinate $coordinate): self
    {
        if (!$this->coordinates->contains($coordinate)) {
            $this->coordinates->add($coordinate);
            $coordinate->setLocation($this);
        }

        return $this;
    }

    /**
     * @return Collection<Coordinate>
     */
    public function getCoordinates(): Collection
    {
        return $this->coordinates;
    }

    public function setCoordinates(array $coordinates): self
    {
        $this->coordinates = new ArrayCollection($coordinates);

        return $this;
    }

    public function getCurrentCoordination(): ?Coordinate
    {
        return $this->currentCoordination;
    }

    public function setCurrentCoordination(?Coordinate $currentCoordination): self
    {
        $this->currentCoordination = $currentCoordination;

        return $this;
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

    public function getParent(): ?Location
    {
        return $this->parent;
    }

    public function setParent(?Location $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function removeCoordinate(Coordinate $coordinate): self
    {
        $this->coordinates->removeElement($coordinate);

        return $this;
    }
}
