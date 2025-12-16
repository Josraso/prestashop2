<?php

namespace prestashop\prestashopWebserviceLib\Images\Domain\OnlyProduct;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class Declination extends PrestashopItemAbstract
{
    public function __construct(
        /**
         * @Annotation\XmlAttribute
         * @Annotation\Type("integer")
         * @Annotation\SerializedName("id")
         */
        public int $id,
        /**
         * @Annotation\XmlAttribute(namespace="http://www.w3.org/1999/xlink")
         * @Annotation\Type("string")
         * @Annotation\SerializedName("href")
         */
        public string $href
    ) {
    }
}