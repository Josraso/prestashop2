<?php

namespace prestashop\prestashopWebserviceLib\Country\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopCountries extends PrestashopAbstract
{
    public function __construct(
        /**
         * @Annotation\Type("prestashop\prestashopWebserviceLib\Country\Domain\Country")
         * @Annotation\SerializedName("country")
         */
        public ?Country $country = null
    ) {
    }
}