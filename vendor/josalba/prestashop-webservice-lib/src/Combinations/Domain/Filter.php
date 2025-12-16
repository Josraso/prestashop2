<?php

namespace prestashop\prestashopWebserviceLib\Combinations\Domain;

use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\FilterAbstract;

class Filter extends FilterAbstract
{
    public function __construct(
        public ?string $id = null,
        public ?string $idProduct = null,
        public ?string $location = null,
        public ?string $ean13 = null,
        public ?string $isbn = null,
        public ?string $upc = null,
        public ?string $quantity = null,
        public ?string $reference = null,
        public ?string $supplierReference = null,
        public ?string $wholesalePrice = null,
        public ?string $price = null,
        public ?string $ecoTax = null,
        public ?string $weight = null,
        public ?string $unitPriceImpact = null,
        public ?string $minimalQuantity = null,
        public ?string $lowStockThreshold = null,
        public ?string $lowStockAlert = null,
        public ?string $defaultOn = null,
        public ?string $availableDate = null
    ) {
    }
}