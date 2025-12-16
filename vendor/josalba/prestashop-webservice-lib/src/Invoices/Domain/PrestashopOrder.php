<?php

namespace prestashop\prestashopWebserviceLib\Invoices\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Combinations\Domain\Combination;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopOrder extends PrestashopAbstract
{
    /**
     * @param array<Combination> $orders
     */
    public function __construct(
        /**
         * @var array<Order>
         * @XmlList(inline = false, entry = "order")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Invoices\Domain\Order>")
         * @Annotation\SerializedName("orders")
         */
        public array $orders
    ) {
    }
}