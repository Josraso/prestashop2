<?php

namespace prestashop\prestashopWebserviceLib\Address\Application;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Address\Domain\PrestashopAddress;
use prestashop\prestashopWebserviceLib\Shared\Application\GetAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayFull;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;

class Get extends GetAbstract
{
    public function __invoke(int $id, ?Display $display = null, ?Filter $filter = null): PrestashopAddress
    {
        return SerializerBuilder::create()->build()->deserialize(
            $this->getRawWithoutParams(Resources::ADDRESSES.'/'.$id),
            PrestashopAddress::class,
            Formats::XML
        );
    }
}