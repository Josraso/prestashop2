<?php

namespace prestashop\prestashopWebserviceLib\Tests\Images\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Images\Domain\AllProduct\PrestashopImages as AllProduct;
use prestashop\prestashopWebserviceLib\Images\Domain\OnlyProduct\PrestashopImages as OnlyProduct;

class PrestashopImagesTest extends TestCase
{
    public function testSerializeXML(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <image id="21">
        <declination id="24" xlink:href="https://local.local/api/images/products/21/24"/>
        <declination id="25" xlink:href="https://local.local/api/images/products/21/25"/>
        <declination id="26" xlink:href="https://local.local/api/images/products/21/26"/>
        <declination id="27" xlink:href="https://local.local/api/images/products/21/27"/>
        <declination id="253" xlink:href="https://local.local/api/images/products/21/253"/>
    </image>
</prestashop>
XML;

        $prestashopImages = OnlyProduct::byXml($xml);

        self::assertNotEmpty($prestashopImages->images);
        self::assertEquals(
            'https://local.local/api/images/products/21/24',
            $prestashopImages->images[0]->declinations[0]->href
        );
    }

    public function testSerializeXMLAllProducts(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <image_types>
        <image_type id="1" name="cart_default" xlink:href="https://local.local/api/image_types/1"/>
        <image_type id="4" name="home_default" xlink:href="https://local.local/api/image_types/4"/>
        <image_type id="5" name="large_default" xlink:href="https://local.local/api/image_types/5"/>
        <image_type id="3" name="medium_default" xlink:href="https://local.local/api/image_types/3"/>
        <image_type id="2" name="small_default" xlink:href="https://local.local/api/image_types/2"/>
    </image_types>
    <images>
        <image id="21" xlink:href="https://local.local/api/images/products/21"/>
        <image id="22" xlink:href="https://local.local/api/images/products/22"/>
        <image id="23" xlink:href="https://local.local/api/images/products/23"/>
        <image id="24" xlink:href="https://local.local/api/images/products/24"/>
    </images>
</prestashop>
XML;

        $prestashopImages = AllProduct::byXml($xml);

        self::assertNotEmpty($prestashopImages->images);
        self::assertEquals(21, $prestashopImages->images[0]->id);
        self::assertEquals(
            'https://local.local/api/images/products/21',
            $prestashopImages->images[0]->href
        );
    }
}