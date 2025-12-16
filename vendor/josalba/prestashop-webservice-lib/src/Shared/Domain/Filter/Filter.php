<?php
/**
 * Control returned items with “filter”
 *
 * The EQUAL operator is used when you need to get specific items. For example, if you want the addresses for customer #1, you can filter your GET request with the filter parameter: http://example.com/api/addresses?filter[id_customer]=1
 *
 * The LIKE operator is used when you need to search for items. For example, if you want the addresses with cities starting with “SAINT”: http://example.com/api/addresses?filter[city]=[saint]%
 *
 * The OR operator is used when you need to get items matching several criteria: http://example.com/api/addresses?filter[city]=[paris|lyon]
 */

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Filter;

interface Filter
{
    public function getFilterQuery(): array;
}