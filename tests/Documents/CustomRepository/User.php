<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\CustomRepository;

use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\IndexStrategy;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Pk;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\ReferenceOne;

#[Document(
    indexStrategy: new IndexStrategy(
        hash: IndexStrategy::PK_STRATEGY_FORMAT,
        range: '{role}#{id}'
    ),
    repositoryClass: UserRepository::class
)]
class User
{
    #[ReferenceOne(targetDocument: Game::class)]
    private ?Game $game = null;

    #[Pk]
    private ?string $id = null;

    #[Field]
    private string $name;

    #[Field]
    private string $role;

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }
}
