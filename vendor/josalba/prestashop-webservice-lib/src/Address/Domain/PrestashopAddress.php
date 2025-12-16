<?php

namespace prestashop\prestashopWebserviceLib\Address\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopAddress extends PrestashopAbstract
{
    public function __construct(
        /**
         * @Annotation\Type("prestashop\prestashopWebserviceLib\Address\Domain\Address")
         * @Annotation\SerializedName("address")
         */
        public ?Address $address = null
    ) {
    }
}