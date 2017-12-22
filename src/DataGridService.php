<?php
namespace Tao2581\DataGrid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;
use Tao2581\Datagrid\DataGrid\Engines\EloquentEngine;
use Tao2581\Datagrid\DataGrid\Engines\RepositoryEngine;
use Tao2581\Datagrid\DataGrid\Engines\QueryBuilderEngine;

class DataGridService
{
    /**
     * Class Constructor
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request->request->count() ? $request : Request::capture();
    }

    static public function test()
    {
        return 'hello test';
    }
    /**
     * Gets query and returns instance of class
     *
     * @param  mixed $builder
     * @return mixed
     */
    public function of($builder)
    {
        if ($builder instanceof Model || $builder instanceof EloquentBuilder || is_string($builder) ) {
            $ins = new EloquentEngine($this->request, $builder);
        }
        elseif ($builder instanceof QueryBuilder)
        {
            $ins = new QueryBuilderEngine($this->request, $builder);
        }
        elseif ($builder instanceof RepositoryInterface)
        {
            $ins = new RepositoryEngine($this->request, $builder);
        }
        else
        {
            die('ins not found');
        }

        return $ins;

    }
}