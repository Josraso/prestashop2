<?php

namespace prestashop\prestashopWebserviceLib\Customer\Application;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Customer\Domain\PrestashopCustomer;
use prestashop\prestashopWebserviceLib\Shared\Application\GetAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;

class Get extends GetAbstract
{
    public function __invoke(int $id, ?Display $display = null, ?Filter $filter = null): PrestashopCustomer
    {
        return SerializerBuilder::create()->build()->deserialize(
            $this->getRawWithoutParams(Resources::CUSTOMER.'/'.$id),
            PrestashopCustomer::class,
            Formats::XML
        );
    }
}