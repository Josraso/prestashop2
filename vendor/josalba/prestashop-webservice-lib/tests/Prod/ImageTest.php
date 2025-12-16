<?php

namespace prestashop\prestashopWebserviceLib\Tests\Prod;

use PHPUnit\Framework\TestCase;
use prestashop\prestashopWebserviceLib\Images\Application\GetProduct;

/**
 * @group ignored-group
 */
class ImageTest extends TestCase
{
    public function testContent(): void
    {
        $data = (new GetProduct('{{url}}', '{{token}}'))->getRaw('{{url-image}}');

        $tempFile = tempnam(sys_get_temp_dir(), 'tmp_');
        file_put_contents($tempFile, $data);

        self::assertEquals(
            md5_file(__DIR__.'/data/response.jpeg'),
            md5_file($tempFile)
        );

        unlink($tempFile);
    }

    public function test(): void
    {
        $data = (new GetProduct('{{url}}', '{{token}}'))->__invoke(21);

        dd($data);
    }
}