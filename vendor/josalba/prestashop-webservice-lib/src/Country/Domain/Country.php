<?php

namespace prestashop\prestashopWebserviceLib\Country\Domain;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

class Country extends PrestashopItemAbstract
{
    public function __construct(
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id")
         */
        public ?string $id = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_zone")
         */
        public ?string $idZone = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_currency")
         */
        public ?string $idCurrency = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("call_prefix")
         */
        public ?string $callPrefix = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("iso_code")
         */
        public ?string $isoCode = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("active")
         */
        public ?string $active = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("contains_states")
         */
        public ?string $containsStates = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("need_identification_number")
         */
        public ?string $needIdentificationNumber = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("need_zip_code")
         */
        public ?string $needZipCode = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("zip_code_format")
         */
        public ?string $zipCodeFormat = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("display_tax_label")
         */
        public ?string $displayTaxLabel = null,
    ) {

    }
}