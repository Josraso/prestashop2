<?php

namespace prestashop\prestashopWebserviceLib\Tests\Prod;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Invoices\Application\Get;

/**
 * @group ignored-group
 */
class OrdersTest extends TestCase
{
    public function test(): void
    {
        $p = (new Get('{{url}}', '{{token}}'))->__invoke(
            null,
            null,
            ['filter[id]' => '>[5155]']
        );

        dd($p);
    }
}