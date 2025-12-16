<?php

namespace prestashop\prestashopWebserviceLib\Shared\Domain\Display;

interface Display
{
    public function toPrimitive(): array;

    public function __toString(): string;
}