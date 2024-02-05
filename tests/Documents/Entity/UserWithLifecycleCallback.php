<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Documents\Entity;

use Aristek\Bundle\DynamodbBundle\ODM\Event\LifecycleEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Event\PreFlushEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Event\PreLoadEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Event\PreUpdateEventArgs;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Document;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Field;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\HasLifecycleCallbacks;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\Pk;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PostLoad;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PostPersist;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PreFlush;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PreLoad;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PrePersist;
use Aristek\Bundle\DynamodbBundle\ODM\Mapping\Annotations\PreUpdate;
use DateTimeImmutable;

#[Document]
#[HasLifecycleCallbacks]
class UserWithLifecycleCallback
{
    #[Field]
    private ?DateTimeImmutable $createdAt = null;

    #[Pk]
    private ?string $id = null;

    private ?string $postLoad = null;

    private ?string $postPersist = null;

    private ?string $preFlush = null;

    private ?string $preLoad = null;

    private ?string $prePersistOther = null;

    private ?string $preUpdate = null;

    private ?string $value = null;

    #[PostLoad]
    public function doOnPostLoad(LifecycleEventArgs $eventArgs): void
    {
        $this->postLoad = 'PostLoad';
    }

    #[PostPersist]
    public function doOnPostPersist(LifecycleEventArgs $eventArg): void
    {
        $this->postPersist = 'PostPersist';
    }

    #[PreFlush]
    public function doOnPreFlush(PreFlushEventArgs $eventArgs): void
    {
        $this->preFlush = 'PreFlush';
    }

    #[PreLoad]
    public function doOnPreLoad(PreLoadEventArgs $eventArgs): void
    {
        $this->preLoad = 'PreLoad';
    }

    #[PrePersist]
    public function doOnPrePersist(LifecycleEventArgs $eventArg): void
    {
        $this->createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
    }

    #[PrePersist]
    public function doOnPrePersistOther(LifecycleEventArgs $eventArg): void
    {
        $this->prePersistOther = 'PrePersistOther';
    }

    #[PreUpdate]
    public function doOnPreUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $this->preUpdate = 'PreUpdate';
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPostLoad(): ?string
    {
        return $this->postLoad;
    }

    public function getPostPersist(): ?string
    {
        return $this->postPersist;
    }

    public function getPreFlush(): ?string
    {
        return $this->preFlush;
    }

    public function getPreLoad(): ?string
    {
        return $this->preLoad;
    }

    public function getPrePersistOther(): ?string
    {
        return $this->prePersistOther;
    }

    public function getPreUpdate(): ?string
    {
        return $this->preUpdate;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;

        return $this;
    }
}
