<?php

namespace prestashop\prestashopWebserviceLib\Shared\Application;

use prestashop\prestashopWebserviceLib\Shared\Domain\Display\Display;
use prestashop\prestashopWebserviceLib\Shared\Domain\Display\DisplayFull;
use prestashop\prestashopWebserviceLib\Shared\Domain\Filter\Filter;
use prestashop\prestashopWebserviceLib\Shared\Domain\Item\PrestashopItemUpdate;
use prestashop\prestashopWebserviceLib\Shared\Domain\ShopParam;
use prestashop\prestashopWebserviceLib\Shared\Infrastructure\ClientHttp;

class PrestaShopWebservice
{
    private ClientHttp $client;
    private bool $useWsKey;
    private string $token;

    public function __construct(string $url, string $key, bool $useWsKey = false)
    {
        $this->token = $key;
        $this->useWsKey = $useWsKey;

        if ($useWsKey) {
            $this->client = new ClientHttp($url.'/api/');

            return;
        }

        $encodedAuth = base64_encode($key.":");

        $this->client = new ClientHttp(
            $url.'/api/',
            [
                'Authorization' => 'Basic '.$encodedAuth,
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultParameters(): array
    {
        if (!$this->useWsKey) {
            return [];
        }

        return ['ws_key' => $this->token];
    }

    public function get(
        string $resource,
        ?Display $display = null,
        ?Filter $filter = null,
        array $queryExtra = []
    ): string {
        $display = $display ?? new DisplayFull();

        $query = array_merge($this->getDefaultParameters(), ['display' => (string)$display]);

        if ($filter !== null) {
            $query += $filter->getFilterQuery();
        }

        if (!empty($queryExtra)) {
            $query += $queryExtra;
        }

        return $this->client->get($resource, $query);
    }

    public function getWithoutParameters(string $resource): string
    {
        return $this->client->get($resource, $this->getDefaultParameters());
    }

    public function edit(string $resource, PrestashopItemUpdate $item, ShopParam $shopParam): string
    {
        return $this->client->put(
            $resource,
            array_merge($this->getDefaultParameters(), $shopParam->getQuery()),
            $item->__toString()
        );
    }
}