<?php

namespace prestashop\prestashopWebserviceLib\Tests\Prod;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Address\Application\Get;

/**
 * @group ignored-group
 */
class CustomerTest extends TestCase
{
    public function test(): void
    {
        $p = (new Get('{{url}}', '{{token}}'))->__invoke(12);

        dd($p);
    }
}