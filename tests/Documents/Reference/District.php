<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Reference;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Id;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Index;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\IndexStrategy;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceMany;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceOne;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Document(
    globalSecondaryIndexes: [
        new Index(
            hash: 'itemTypePk',
            name: 'ItemTypeIndex',
            strategy: new IndexStrategy(hash: 'DISTRICT', range: '{CLASS}#{id}'),
            range: 'itemTypeSk'
        ),
    ],
)]
class District
{
    #[Id]
    private ?string $id = null;

    #[Field]
    private string $name;

    #[ReferenceOne(targetDocument: School::class, cascade: "all", orphanRemoval: true)]
    private ?School $schoolWithOrphanRemove = null;

    /**
     * @var Collection<School>
     */
    #[ReferenceMany(targetDocument: School::class, cascade: "all", orphanRemoval: true)]
    private Collection $schoolWithOrphanRemoves;

    /**
     * @var Collection<School>
     */
    #[ReferenceMany(targetDocument: School::class, cascade: "all")]
    private Collection $schools;

    public function __construct()
    {
        $this->schools = new ArrayCollection();
        $this->schoolWithOrphanRemoves = new ArrayCollection();
    }

    public function addSchool(School $school): self
    {
        if (!$this->schools->contains($school)) {
            $this->schools->add($school);
            $school->setDistrict($this);
        }

        return $this;
    }

    public function addSchoolWithOrphanRemove(School $school): self
    {
        if (!$this->schoolWithOrphanRemoves->contains($school)) {
            $this->schoolWithOrphanRemoves->add($school);
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSchoolWithOrphanRemove(): ?School
    {
        return $this->schoolWithOrphanRemove;
    }

    public function setSchoolWithOrphanRemove(?School $schoolWithOrphanRemove): self
    {
        $this->schoolWithOrphanRemove = $schoolWithOrphanRemove;

        return $this;
    }

    public function getSchoolWithOrphanRemoves(): Collection
    {
        return $this->schoolWithOrphanRemoves;
    }

    /**
     * @return Collection<School>
     */
    public function getSchools(): Collection
    {
        return $this->schools;
    }

    public function removeSchool(School $school): self
    {
        $this->schools->removeElement($school);

        return $this;
    }

    public function removeSchoolWithOrphanRemove(School $school): self
    {
        $this->schoolWithOrphanRemoves->removeElement($school);

        return $this;
    }
}
