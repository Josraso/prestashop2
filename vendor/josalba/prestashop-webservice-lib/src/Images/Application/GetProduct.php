<?php

namespace prestashop\prestashopWebserviceLib\Images\Application;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Images\Domain\OnlyProduct\PrestashopImages;
use prestashop\prestashopWebserviceLib\Shared\Application\GetAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;

class GetProduct extends GetAbstract
{
    public function __invoke(int $id, ?Display $display = null, ?Filter $filter = null): PrestashopImages
    {
        return SerializerBuilder::create()->build()->deserialize(
            $this->getRawWithoutParams(Resources::IMAGES.'/products/'.$id),
            PrestashopImages::class,
            Formats::XML
        );
    }
}