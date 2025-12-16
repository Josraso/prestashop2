<?php

namespace prestashop\prestashopWebserviceLib\Images\Application;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Images\Domain\AllProduct\PrestashopImages;
use prestashop\prestashopWebserviceLib\Shared\Application\GetAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;

class GetProducts extends GetAbstract
{
    public function __invoke(?Display $display = null, ?Filter $filter = null): PrestashopImages
    {
        return SerializerBuilder::create()->build()->deserialize(
            $this->getRawWithoutParams(Resources::IMAGES.'/products'),
            PrestashopImages::class,
            Formats::XML
        );
    }
}