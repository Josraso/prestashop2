<?php

namespace prestashop\prestashopWebserviceLib\Address\Domain;

use JMS\Serializer\Annotation;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemAbstract;

class Address extends PrestashopItemAbstract
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
         * @Annotation\SerializedName("id_customer")
         */
        public ?string $idCustomer = null,
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
         * @Annotation\SerializedName("id_warehouse")
         */
        public ?string $idWarehouse = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_country")
         */
        public ?string $idCountry = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("id_state")
         */
        public ?string $idState = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("alias")
         */
        public ?string $alias = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("company")
         */
        public ?string $company = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("lastname")
         */
        public ?string $lastname = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("firstname")
         */
        public ?string $firstname = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("vat_number")
         */
        public ?string $vatNumber = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("address1")
         */
        public ?string $address1 = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("address2")
         */
        public ?string $address2 = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("postcode")
         */
        public ?string $postcode = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("city")
         */
        public ?string $city = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("other")
         */
        public ?string $other = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("phone")
         */
        public ?string $phone = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("phone_mobile")
         */
        public ?string $phoneMobile = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("dni")
         */
        public ?string $dni = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("deleted")
         */
        public ?string $deleted = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("date_add")
         */
        public ?string $dateAdd = null,
        /**
         * @Annotation\XmlElement(cdata=true)
         * @Annotation\Type("string")
         * @Annotation\SerializedName("date_upd")
         */
        public ?string $dateUpd = null,
    ){

    }
}