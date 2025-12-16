<?php

namespace prestashop\prestashopWebserviceLib\Combinations\Domain;

use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayAbstract;

class Display extends DisplayAbstract
{
    public function __construct(
        public bool $id = false,
        public bool $idProduct = false,
        public bool $location = false,
        public bool $upc = false,
        public bool $quantity = false,
        public bool $reference = false,
        public bool $supplierReference = false,
        public bool $wholesalePrice = false,
        public bool $price = false,
        public bool $ecotax = false,
        public bool $weight = false,
        public bool $unitPriceImpact = false,
        public bool $minimalQuantity = false,
        public bool $lowStockThreshold = false,
        public bool $lowStockAlert = false,
        public bool $defaultOn = false,
        public bool $availableDate = false,
        public bool $associations = false,
        public bool $ean13 = false,
        public bool $isbn = false,
        public bool $mpn = false,
    ) {
    }
}