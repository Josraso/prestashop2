<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Item;

use JMS\Serializer\SerializerBuilder;
use prestashop\prestashopWebserviceLib\Shared\Domain\Formats;
use prestashop\prestashopWebserviceLib\Shared\Domain\Prestashop;

abstract class PrestashopAbstract implements Prestashop
{
    public function __toString(): string
    {
        return SerializerBuilder::create()->build()->serialize($this, Formats::XML);
    }

    public static function byXml(string $xml): static
    {
        return SerializerBuilder::create()->build()->deserialize($xml, static::class, Formats::XML);
    }
}