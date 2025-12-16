<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Filter;

abstract class FilterAbstract implements Filter
{
    /**
     * @return array<string, string>
     */
    public function getFilterQuery(): array
    {
        $query = [];

        $vars = get_object_vars($this);

        foreach($vars as $key => $value) {
            $snakeCaseKey = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($key)));
            $query['filter['.$snakeCaseKey.']'] = $value;
        }

        return \array_filter($query);
    }
}