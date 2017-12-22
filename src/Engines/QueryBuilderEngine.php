<?php
namespace Tao2581\Datagrid\DataGrid\Engines;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Tao2581\Datagrid\DataGrid\Contracts\DataGridEngineContract;

class QueryBuilderEngine extends BaseEngine implements DataGridEngineContract
{
    /**
     * @param mixed $model
     * @param Request $request
     */
    public function __construct(Request $request, $model)
    {
        if ($model instanceof QueryBuilder)
        {
            $builder = $model;
        }
        else
        {
            return false;
        }
        $this->init($request,$builder);
    }

}