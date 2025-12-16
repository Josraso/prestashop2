<?php

namespace prestashop\prestashopWebserviceLib\StockAvaliables\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemUpdate;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopStockAvailableUpdate extends PrestashopAbstract implements PrestashopItemUpdate
{
    /**
     * @var array<StockAvailable>
     * @XmlList(inline = true, entry = "stock_available")
     * @Annotation\Type("array<prestashop\prestashopWebserviceLib\StockAvaliables\Domain\StockAvailable>")
     * @Annotation\SerializedName("stock_available")
     */
    public array $stockAvailable;

    public function __construct(StockAvailable $stockAvailable)
    {
        $this->stockAvailable[] = $stockAvailable;
    }
}