<?php

namespace prestashop\prestashopWebserviceLib\StockAvaliables\Application;

use prestashop\prestashopWebserviceLib\Shared\Application\PutAbstract;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemUpdate;
use prestashop\prestashopWebserviceLib\Shared\Domain\Resources;
use prestashop\prestashopWebserviceLib\Shared\Domain\ShopParam;

class Put extends PutAbstract
{
    public function __invoke(PrestashopItemUpdate $item, ShopParam $shopParam): void
    {
        $this->put(Resources::STOCK_AVAILABLES, $item, $shopParam);
    }
}