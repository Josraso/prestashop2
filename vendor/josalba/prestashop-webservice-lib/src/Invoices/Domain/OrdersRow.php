<?php

namespace prestashop\prestashopWebserviceLib\Invoices\Domain;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class OrdersRow extends PrestashopItemAbstract
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
         * @Annotation\SerializedName("product_id")
         */
        public ?string $productId = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_attribute_id")
         */
        public ?string $product_attribute_id = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_quantity")
         */
        public ?string $product_quantity = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_name")
         */
        public ?string $productName = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_reference")
         */
        public ?string $productReference = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_ean13")
         */
        public ?string $productEan13 = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_isbn")
         */
        public ?string $productIsbn = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_upc")
         */
        public ?string $productUpc = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("product_price")
         */
        public ?string $productPrice = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_customization")
         */
        public ?string $idCustomization = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("unit_price_tax_incl")
         */
        public ?string $unitPriceTaxIncl = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("unit_price_tax_excl")
         */
        public ?string $unitPriceTaxExcl = null,
    ) {
    }
}