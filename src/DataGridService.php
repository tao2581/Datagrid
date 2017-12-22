<?php
namespace Tao2581\DataGrid;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;
use Tao2581\DataGrid\Engines\EloquentEngine;
use Tao2581\DataGrid\Engines\RepositoryEngine;
use Tao2581\DataGrid\Engines\QueryBuilderEngine;

class DataGridService
{
    /**
     * Class Constructor
     *
     * @param Request $request
     */
    public function __construct()
    {
        $request = Request::capture();
        $this->request = $request->request->count() ? $request : Request::capture();
    }

    /**
     * Gets query and returns instance of class
     *
     * @param  mixed $builder
     * @return mixed
     */
    static public function of($builder)
    {
        $self = new self();
        if ($builder instanceof Model || $builder instanceof EloquentBuilder || is_string($builder) ) {
            $ins = new EloquentEngine($self->request, $builder);
        }
        elseif ($builder instanceof QueryBuilder)
        {
            $ins = new QueryBuilderEngine($self->request, $builder);
        }
        elseif ($builder instanceof RepositoryInterface)
        {
            $ins = new RepositoryEngine($self->request, $builder);
        }
        else
        {
            die('ins not found');
        }

        return $ins;
    }
}