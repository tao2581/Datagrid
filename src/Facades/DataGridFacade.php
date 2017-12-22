<?php

namespace Tao2581\DataGrid\Facades;

use Illuminate\Support\Facades\Facade;

class DataGridFacade extends Facade {

    protected static function getFacadeAccessor() { return 'DataGridService'; }

}