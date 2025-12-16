<?php

namespace prestashop\prestashopWebserviceLib\Tests\Shared\Domain;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Tests\Shared\Domain\Filter;

class DisplayTest extends TestCase
{
    public function test(): void
    {
        $display = (new Display(true,true))->toPrimitive();

        self::assertNotEmpty($display);
        self::assertContains('id_category_default',$display);
        self::assertContains('advanced_stock_management',$display);
    }
}