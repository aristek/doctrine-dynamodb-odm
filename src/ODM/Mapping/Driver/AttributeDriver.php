<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\ODM\Mapping\Driver;

use Doctrine\Common\Annotations\Reader;

/**
 * The AnnotationDriver reads the mapping metadata from docblock annotations.
 */
class AttributeDriver extends AnnotationDriver
{
    /**
     * Factory method for the Attribute Driver
     *
     * @param string[]|string $paths
     */
    public static function create(array|string $paths = [], ?Reader $reader = null): AnnotationDriver
    {
        return new self($paths, $reader);
    }

    /**
     * @param string|string[]|null $paths
     */
    public function __construct(array|string|null $paths = null, ?Reader $reader = null)
    {
        parent::__construct($reader ?? new AttributeReader(), $paths);
    }
}
