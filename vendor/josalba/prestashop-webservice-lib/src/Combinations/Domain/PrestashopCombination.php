<?php

namespace prestashop\prestashopWebserviceLib\Combinations\Domain;

use JMS\Serializer\Annotation;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlNamespace;
use JMS\Serializer\Annotation\XmlRoot;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopAbstract;

/**
 * @XmlRoot("prestashop")
 * @XmlNamespace(uri="http://www.w3.org/1999/xlink", prefix="xlink")
 */
class PrestashopCombination extends PrestashopAbstract
{
    /**
     * @param array<Combination> $combinations
     */
    public function __construct(
        /**
         * @var array<Combination>
         * @XmlList(inline = false, entry = "combination")
         * @Annotation\Type("array<prestashop\prestashopWebserviceLib\Combinations\Domain\Combination>")
         * @Annotation\SerializedName("combinations")
         */
        public array $combinations
    ) {
    }
}