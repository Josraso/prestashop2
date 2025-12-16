<?php

namespace prestashop\prestashopWebserviceLib\Tests\Shared\Domain;

use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function test(): void
    {
        $filter = (new Filter('id_test','id_product_test'))->getFilterQuery();

        self::assertNotEmpty($filter);
        self::assertArrayHasKey('filter[id]',$filter);
        self::assertArrayHasKey('filter[id_product]',$filter);
    }
}