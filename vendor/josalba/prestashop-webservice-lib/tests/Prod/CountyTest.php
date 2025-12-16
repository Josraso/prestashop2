<?php

namespace prestashop\prestashopWebserviceLib\Tests\Prod;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Country\Application\Get;

/**
 * @group ignored-group
 */
class CountyTest extends TestCase
{
    public function test(): void
    {
        $p = (new Get('{{url}}', '{{token}}'))->__invoke(6);

        dd($p);
    }
}