<?php

namespace prestashop\prestashopWebserviceLib\Products\Domain;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

final class Product extends PrestashopItemAbstract
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
         * @Annotation\SerializedName("id_manufacturer")
         */
        public ?string $idManufacturer = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_supplier")
         */
        public ?string $idSupplier = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_category_default")
         */
        public ?string $idCategoryDefault = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("manufacturer_name")
         */
        public ?string $manufacturerName = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_default_image")
         */
        public ?string $idDefaultImage = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("quantity")
         */
        public ?string $quantity = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("price")
         */
        public ?string $price = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("reference")
         */
        public ?string $reference = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("ean13")
         */
        public ?string $ean13 = null,
    ) {
    }
}