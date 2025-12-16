<?php

namespace prestashop\prestashopWebserviceLib\Shared\Application;

use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayFull;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;

abstract class GetAbstract
{
    private PrestaShopWebservice $webService;

    public function __construct(string $url, string $key, bool $useWsKey = false)
    {
        $this->webService = new PrestaShopWebservice($url, $key, $useWsKey);
    }

    final public function getRaw(
        string $resource,
        ?Display $display = null,
        ?Filter $filter = null,
        array $queryExtra = []
    ): string {
        return $this->webService->get($resource, $display ?? new DisplayFull(), $filter, $queryExtra);
    }

    final public function getRawWithoutParams(string $resource): string {
        return $this->webService->getWithoutParameters($resource);
    }
}