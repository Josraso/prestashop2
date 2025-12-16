<?php

namespace prestashop\prestashopWebserviceLib\Images\Domain\AllProduct;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopImages extends PrestashopAbstract
{
    /**
     * @param array<Image> $images
     */
    public function __construct(
        /**
         * @var array<Image>
         * @XmlList(inline = false, entry = "image")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Images\Domain\AllProduct\Image>")
         * @Annotation\SerializedName("images")
         */
        public array $images = []
    ) {
    }
}