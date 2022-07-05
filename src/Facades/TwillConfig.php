<?php

namespace A17\Twill\Facades;

use Illuminate\Support\Facades\Facade;

class TwillConfig extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \A17\Twill\TwillConfig::class;
    }
}
