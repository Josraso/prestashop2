<?php

namespace prestashop\prestashopWebserviceLib\Images\Domain\OnlyProduct;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class Image extends PrestashopItemAbstract
{
    /**
     * @param array<Declination> $declinations
     */
    public function __construct(
        /**
         * @Annotation\XmlAttribute
         * @Annotation\Type("integer")
         */
        public int $id,
        /**
         * @var array<Declination>
         * @Annotation\XmlList(inline=true, entry="declination")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Images\Domain\OnlyProduct\Declination>")
         */
        public array $declinations = []
    ) {
    }
}