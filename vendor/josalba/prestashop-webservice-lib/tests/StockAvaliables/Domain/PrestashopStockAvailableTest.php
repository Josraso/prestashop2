<?php

namespace prestashop\prestashopWebserviceLib\Tests\StockAvaliables\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\StockAvaliables\Domain\PrestashopStockAvailable;
use prestashop\prestashopWebserviceLib\StockAvaliables\Domain\StockAvailable;

class PrestashopStockAvailableTest extends TestCase
{
    public function testSerializeXML(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <stock_availables>
      <stock_available>
        <id_product><![CDATA[1]]></id_product>
        <quantity><![CDATA[10]]></quantity>
      </stock_available>
  </stock_availables>
</prestashop>
XML;

        $stockAvailable = new PrestashopStockAvailable(
            [
                new StockAvailable(
                    idProduct         : '1',
                    quantity          : '10'
                ),
            ]
        );

        $stockAvailableXml = PrestashopStockAvailable::byXml($xml);

        self::assertNotEmpty($stockAvailableXml->stockAvailable);
        self::assertEquals($stockAvailableXml->stockAvailable[0]->idProduct, $stockAvailable->stockAvailable[0]->idProduct);
        self::assertEquals($stockAvailableXml->stockAvailable[0]->quantity, $stockAvailable->stockAvailable[0]->quantity);
    }
}