<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Item;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;

abstract class PrestashopItemAbstract implements PrestashopItem
{
    public function __toString(): string
    {
        return SerializerBuilder::create()->build()->serialize($this, Formats::XML);
    }

    public function __serialize(): array
    {
        return [];
    }
}