<?php

namespace prestashop\prestashopWebserviceLib\StockAvaliables\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopStockAvailable extends PrestashopAbstract
{
    /**
     * @param array<StockAvailable> $stockAvailable
     */
    public function __construct(
        /**
         * @var array<StockAvailable>
         * @XmlList(inline = false, entry = "stock_available")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\StockAvaliables\Domain\StockAvailable>")
         * @Annotation\SerializedName("stock_availables")
         */
        public readonly array $stockAvailable = []
    ) {
    }
}