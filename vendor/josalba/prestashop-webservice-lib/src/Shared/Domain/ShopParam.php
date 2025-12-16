<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain;

readonly class ShopParam
{
    public function __construct(public ?int $shopId = null, public ?int $groupShopId = null)
    {
    }

    public function getQuery(): array
    {
        return array_filter(['id_shop' => $this->shopId, 'id_group_shop' => $this->groupShopId,]);
    }
}