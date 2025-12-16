<?php

namespace prestashop\prestashopWebserviceLib\Customer\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopCustomer extends PrestashopAbstract
{
    public function __construct(
        /**
         * @Annotation\Type("prestashop\prestashopWebserviceLib\Customer\Domain\Customer")
         * @Annotation\SerializedName("customer")
         */
        public ?Customer $customer = null
    ) {
    }
}