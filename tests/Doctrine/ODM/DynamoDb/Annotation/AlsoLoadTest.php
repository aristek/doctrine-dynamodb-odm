<?php

declare(strict_types=1);

namespace Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\Annotation;

use Aristek\Bundle\DynamodbBundle\ODM\Id\PrimaryKey;
use Aristek\Bundle\DynamodbBundle\Tests\Doctrine\ODM\DynamoDb\BaseTestCase;
use Aristek\Bundle\DynamodbBundle\Tests\Documents\Annotation\Product;

final class AlsoLoadTest extends BaseTestCase
{
    public function testAlsoLoad(): void
    {
        $product = $this->prepare();

        self::assertEquals('secondAlias', $product->getSecondAlias());
        self::assertEquals('secondAlias', $product->getAlias());
        self::assertEquals('secondAlias', $product->getName());
    }

    public function testAlsoLoadOnMethod(): void
    {
        $product = $this->prepare();

        self::assertEquals('secondAlias', $product->getRealName());
    }

    protected function prepare(): Product
    {
        $product = (new Product())->setSecondAlias('secondAlias');
        $this->dm->persist($product);
        $this->dm->flush();

        $productId = $product->getId();

        $this->dm->clear();

        /** @var Product $product */
        $product = $this->dm->getRepository(Product::class)->find(new PrimaryKey($productId, 'Product'));

        return $product;
    }
}
