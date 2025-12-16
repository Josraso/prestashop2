<?php

namespace prestashop\prestashopWebserviceLib\Tests\Shared\Domain;

use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\FilterAbstract;

class Filter extends FilterAbstract
{
    public function __construct(
        public readonly string $id,
        public readonly string $idProduct,
    )
    {
    }
}