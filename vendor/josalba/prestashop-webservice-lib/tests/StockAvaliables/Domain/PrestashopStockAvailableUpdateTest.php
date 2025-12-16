<?php

namespace prestashop\prestashopWebserviceLib\Tests\StockAvaliables\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\StockAvaliables\Domain\PrestashopStockAvailableUpdate;
use prestashop\prestashopWebserviceLib\StockAvaliables\Domain\StockAvailable;

class PrestashopStockAvailableUpdateTest extends TestCase
{
    public function testSerializeXML(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
  <stock_available>
    <id><![CDATA[1]]></id>
    <id_product><![CDATA[0]]></id_product>
    <id_product_attribute><![CDATA[0]]></id_product_attribute>
    <id_shop><![CDATA[1]]></id_shop>
    <quantity><![CDATA[10]]></quantity>
    <depends_on_stock><![CDATA[0]]></depends_on_stock>
    <out_of_stock><![CDATA[0]]></out_of_stock>
  </stock_available>
</prestashop>
XML;

        $stockAvailable = new PrestashopStockAvailableUpdate(new StockAvailable(id: '1', quantity: '10'));

        $stockAvailableXml = PrestashopStockAvailableUpdate::byXml($xml);

        self::assertNotEmpty($stockAvailableXml->stockAvailable);
        self::assertEquals($stockAvailableXml->stockAvailable[0]->id, $stockAvailable->stockAvailable[0]->id);
        self::assertEquals(
            $stockAvailableXml->stockAvailable[0]->quantity,
            $stockAvailable->stockAvailable[0]->quantity
        );
    }
}