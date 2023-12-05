<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\PersistentCollection;

/**
 * Interface for PersistentCollection classes generator.
 */
interface PersistentCollectionGenerator
{
    /**
     * Generates persistent collection class.
     */
    public function generateClass(string $class, string $dir): void;

    /**
     * Loads persistent collection class.
     *
     * @param string $collectionClass FQCN of base collection class
     *
     * @return string FQCN of generated class
     */
    public function loadClass(string $collectionClass, int $autoGenerate): string;
}
