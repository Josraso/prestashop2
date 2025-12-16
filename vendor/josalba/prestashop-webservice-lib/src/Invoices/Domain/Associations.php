<?php

namespace prestashop\prestashopWebserviceLib\Invoices\Domain;

use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class Associations extends PrestashopItemAbstract
{
    public function __construct(
        /**
         * @var array<OrdersRow>
         * @XmlList(inline = false, entry = "order_row")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Invoices\Domain\OrdersRow>")
         * @Annotation\SerializedName("order_rows")
         */
        public array $orderRows = [],
    ) {
    }
}