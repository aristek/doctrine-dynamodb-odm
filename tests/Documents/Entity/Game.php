<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\IndexStrategy;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Pk;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceMany;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Repository\GameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Document(
    indexStrategy: new IndexStrategy(hash: '{CLASS}_WITH_REPO', range: '{CLASS_SHORT_NAME}#{id}'),
    repositoryClass: GameRepository::class
)]
class Game
{
    #[ReferenceMany(targetDocument: User::class, cascade: 'all', repositoryMethod: 'getAdminUsers')]
    private Collection $adminUsers;

    #[Pk]
    private ?string $id = null;

    #[Field]
    private string $name;

    #[ReferenceMany(targetDocument: User::class, cascade: 'all')]
    private Collection $users;

    public function __construct()
    {
        $this->adminUsers = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setGame($this);
        }

        return $this;
    }

    public function getAdminUsers(): Collection
    {
        return $this->adminUsers;
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

    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function removeUser(User $user): self
    {
        $this->users->removeElement($user);

        return $this;
    }
}
