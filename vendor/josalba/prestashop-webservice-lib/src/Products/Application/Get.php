<?php

namespace prestashop\prestashopWebserviceLib\Products\Application;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Products\Domain\PrestashopProduct;
use prestashop\prestashopWebserviceLib\Shared\Application\GetAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayFull;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;

class Get extends GetAbstract
{
    public function __invoke(?Display $display = null, ?Filter $filter = null): PrestashopProduct
    {
        return SerializerBuilder::create()->build()->deserialize(
            $this->getRaw(Resources::PRODUCTS,$display ?? new DisplayFull(), $filter),
            PrestashopProduct::class,
            Formats::XML
        );
    }
}