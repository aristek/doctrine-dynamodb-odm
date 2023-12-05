<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BuildTest extends KernelTestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function testContainerBuild(): void
    {
        self::bootKernel();
    }
}
