<?php

namespace prestashop\prestashopWebserviceLib\Tests\Shared\Domain;

use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayAbstract;

class Display extends DisplayAbstract
{
    public function __construct(
        public bool $idCategoryDefault = false,
        public bool $advancedStockManagement = false,
    ) {
    }
}