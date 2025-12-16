<?php

namespace prestashop\prestashopWebserviceLib\StockAvaliables\Domain;

use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayAbstract;

class Display extends DisplayAbstract
{
    public function __construct(
        public bool $id = false,
        public bool $idProduct = false,
        public bool $idProductAttribute = false,
        public bool $idShop = false,
        public bool $idShopGroup = false,
        public bool $quantity = false,
        public bool $dependsOnStock = false,
        public bool $outOfStock = false,
    ) {
    }
}