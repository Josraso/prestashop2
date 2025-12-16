<?php

namespace prestashop\prestashopWebserviceLib\Products\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopProduct extends PrestashopAbstract
{
    /**
     * @param array<Product> $products
     */
    public function __construct(
        /**
         * @var array<Product>
         * @XmlList(inline = false, entry = "product")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Products\Domain\Product>")
         * @Annotation\SerializedName("products")
         */
        public array $products
    ) {
    }
}