<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Display;

abstract class DisplayAbstract implements Display
{
    /**
     * @return array<string>
     */
    public function toPrimitive(): array
    {
        $display = [];

        $vars = get_object_vars($this);

        foreach($vars as $key => $value) {
            $snakeCaseKey = strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($key)));
            $display[$snakeCaseKey] = $value;
        }

        return array_keys(array_filter($display));
    }

    public function __toString(): string
    {
        return '['.implode(', ', $this->toPrimitive()).']';
    }
}