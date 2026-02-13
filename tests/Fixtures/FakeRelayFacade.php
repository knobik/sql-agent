<?php

namespace Prism\Relay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Fake Relay facade for testing — prism-php/relay is not a dev dependency.
 */
class Relay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'relay';
    }
}
