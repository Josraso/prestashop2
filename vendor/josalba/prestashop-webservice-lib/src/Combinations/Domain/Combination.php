<?php

namespace prestashop\prestashopWebserviceLib\Combinations\Domain;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class Combination extends PrestashopItemAbstract
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
         * @Annotation\SerializedName("id_product")
         */
        public ?string $idProduct = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("location")
         */
        public ?string $location = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("ean13")
         */
        public ?string $ean13 = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("isbn")
         */
        public ?string $isbn = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("upc")
         */
        public ?string $upc = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("quantity")
         */
        public ?string $quantity = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("reference")
         */
        public ?string $reference = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("supplier_reference")
         */
        public ?string $supplierReference = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("wholesale_price")
         */
        public ?string $wholesalePrice = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("price")
         */
        public ?string $price = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("ecotax")
         */
        public ?string $ecotax = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("weight")
         */
        public ?string $weight = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("unit_price_impact")
         */
        public ?string $unitPriceImpact = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("minimal_quantity")
         */
        public ?string $minimalQuantity = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("low_stock_threshold")
         */
        public ?string $lowStockThreshold = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("low_stock_alert")
         */
        public ?string $lowStockAlert = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("default_on")
         */
        public ?string $defaultOn = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("available_date")
         */
        public ?string $availableDate = null
    ) {
    }
}