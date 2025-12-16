<?php

namespace prestashop\prestashopWebserviceLib\Tests\Orders\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Invoices\Domain\PrestashopOrder;

class PrestashopOrdersTest extends TestCase
{
    public function testSerializeXML(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <orders>
        <order>
            <id>
                <![CDATA[6]]>
            </id>
            <id_address_delivery xlink:href="https://testa.com/api/addresses/12">
                <![CDATA[12]]>
            </id_address_delivery>
            <id_address_invoice xlink:href="https://testa.com/api/addresses/12">
                <![CDATA[12]]>
            </id_address_invoice>
            <id_cart xlink:href="https://testa.com/api/carts/38">
                <![CDATA[38]]>
            </id_cart>
            <id_currency xlink:href="https://testa.com/api/currencies/1">
                <![CDATA[1]]>
            </id_currency>
            <id_lang xlink:href="https://testa.com/api/languages/1">
                <![CDATA[1]]>
            </id_lang>
            <id_customer xlink:href="https://testa.com/api/customers/6">
                <![CDATA[6]]>
            </id_customer>
            <id_carrier xlink:href="https://testa.com/api/carriers/14">
                <![CDATA[14]]>
            </id_carrier>
            <current_state xlink:href="https://testa.com/api/order_states/5">
                <![CDATA[5]]>
            </current_state>
            <module>
                <![CDATA[ps_checkout]]>
            </module>
            <invoice_number>
                <![CDATA[419]]>
            </invoice_number>
            <invoice_date>
                <![CDATA[2020-10-13 19:54:39]]>
            </invoice_date>
            <delivery_number>
                <![CDATA[1]]>
            </delivery_number>
            <delivery_date>
                <![CDATA[2020-02-10 18:37:37]]>
            </delivery_date>
            <valid>
                <![CDATA[1]]>
            </valid>
            <date_add>
                <![CDATA[2020-02-02 21:34:47]]>
            </date_add>
            <date_upd>
                <![CDATA[2020-10-13 19:54:39]]>
            </date_upd>
            <shipping_number notFilterable="true">
                <![CDATA[0]]>
            </shipping_number>
            <id_shop_group>
                <![CDATA[1]]>
            </id_shop_group>
            <id_shop>
                <![CDATA[1]]>
            </id_shop>
            <secure_key>
                <![CDATA[c25bf467eb3e4e74f809b21b91b341f3]]>
            </secure_key>
            <payment>
                <![CDATA[Payment by PayPal]]>
            </payment>
            <recyclable>
                <![CDATA[0]]>
            </recyclable>
            <gift>
                <![CDATA[0]]>
            </gift>
            <gift_message></gift_message>
            <mobile_theme>
                <![CDATA[0]]>
            </mobile_theme>
            <total_discounts>
                <![CDATA[0.000000]]>
            </total_discounts>
            <total_discounts_tax_incl>
                <![CDATA[0.000000]]>
            </total_discounts_tax_incl>
            <total_discounts_tax_excl>
                <![CDATA[0.000000]]>
            </total_discounts_tax_excl>
            <total_paid>
                <![CDATA[16.230000]]>
            </total_paid>
            <total_paid_tax_incl>
                <![CDATA[16.230000]]>
            </total_paid_tax_incl>
            <total_paid_tax_excl>
                <![CDATA[13.410000]]>
            </total_paid_tax_excl>
            <total_paid_real>
                <![CDATA[16.230000]]>
            </total_paid_real>
            <total_products>
                <![CDATA[8.260000]]>
            </total_products>
            <total_products_wt>
                <![CDATA[10.000000]]>
            </total_products_wt>
            <total_shipping>
                <![CDATA[6.230000]]>
            </total_shipping>
            <total_shipping_tax_incl>
                <![CDATA[6.230000]]>
            </total_shipping_tax_incl>
            <total_shipping_tax_excl>
                <![CDATA[5.150000]]>
            </total_shipping_tax_excl>
            <carrier_tax_rate>
                <![CDATA[21.000]]>
            </carrier_tax_rate>
            <total_wrapping>
                <![CDATA[0.000000]]>
            </total_wrapping>
            <total_wrapping_tax_incl>
                <![CDATA[0.000000]]>
            </total_wrapping_tax_incl>
            <total_wrapping_tax_excl>
                <![CDATA[0.000000]]>
            </total_wrapping_tax_excl>
            <round_mode>
                <![CDATA[2]]>
            </round_mode>
            <round_type>
                <![CDATA[1]]>
            </round_type>
            <conversion_rate>
                <![CDATA[1.000000]]>
            </conversion_rate>
            <reference>
                <![CDATA[KBHVCMDMO]]>
            </reference>
            <associations>
                <order_rows nodeType="order_row" virtualEntity="true">
                    <order_row>
                        <id>
                            <![CDATA[8]]>
                        </id>
                        <product_id xlink:href="https://testa.com/api/products/121">
                            <![CDATA[121]]>
                        </product_id>
                        <product_attribute_id>
                            <![CDATA[521]]>
                        </product_attribute_id>
                        <product_quantity>
                            <![CDATA[1]]>
                        </product_quantity>
                        <product_name>
                            <![CDATA[Chaleco naranja fluoresente Talla : L]]>
                        </product_name>
                        <product_reference>
                            <![CDATA[734/7]]>
                        </product_reference>
                        <product_ean13>
                            <![CDATA[8427917734071]]>
                        </product_ean13>
                        <product_isbn></product_isbn>
                        <product_upc></product_upc>
                        <product_price>
                            <![CDATA[8.264462]]>
                        </product_price>
                        <id_customization xlink:href="https://testa.com/api/customizations/0">
                            <![CDATA[0]]>
                        </id_customization>
                        <unit_price_tax_incl>
                            <![CDATA[9.999999]]>
                        </unit_price_tax_incl>
                        <unit_price_tax_excl>
                            <![CDATA[8.264462]]>
                        </unit_price_tax_excl>
                    </order_row>
                </order_rows>
            </associations>
        </order>
    </orders>
</prestashop>
XML;

        $OrderXml = PrestashopOrder::byXml($xml);

        self::assertNotEmpty($OrderXml->orders);
        self::assertEquals(6, $OrderXml->orders[0]->id);
        self::assertNotEmpty($OrderXml->orders[0]->associations->orderRows);
    }
}