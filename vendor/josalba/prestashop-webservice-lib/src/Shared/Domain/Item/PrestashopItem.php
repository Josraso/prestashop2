<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Item;

interface PrestashopItem
{
    public function __serialize(): array;

    public function __toString(): string;
}