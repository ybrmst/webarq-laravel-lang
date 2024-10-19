<?php

/**
 * Created by PhpStorm.
 * User: DanielSimangunsong
 * Date: 1/24/2017
 * Time: 10:30 AM
 */

namespace Webarq\Lang\Laravel;


use Illuminate\Support\Facades\Facade;

class WlFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wl';
    }

}