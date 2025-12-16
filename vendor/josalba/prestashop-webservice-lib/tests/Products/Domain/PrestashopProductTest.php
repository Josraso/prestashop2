<?php

namespace prestashop\prestashopWebserviceLib\Tests\Products\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Products\Domain\PrestashopProduct;
use prestashop\prestashopWebserviceLib\Products\Domain\Product;

class PrestashopProductTest extends TestCase
{
    public function testSerializeXML(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <products>
        <product>
            <id><![CDATA[430]]></id>
            <quantity notFilterable="true"><![CDATA[0]]></quantity>
        </product>
    </products>
</prestashop>
XML;

        $productObject = new PrestashopProduct(
            [
                new Product(id: '430', quantity: '0'),
            ]
        );

        $productXml = PrestashopProduct::byXml($xml);

        self::assertNotEmpty($productXml->products);
        self::assertEquals($productXml->products[0]->id, $productObject->products[0]->id);
        self::assertEquals($productXml->products[0]->quantity, $productObject->products[0]->quantity);
    }
}