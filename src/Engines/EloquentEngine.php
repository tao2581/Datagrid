<?php
namespace Tao2581\DataGrid\Engines;

use Tao2581\DataGrid\Contracts\DataGridEngineContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
Use Schema;

class EloquentEngine extends BaseEngine implements DataGridEngineContract
{
    /**
     * @param mixed $model
     * @param Request $request
     */
    public function __construct(Request $request, $model)
    {
        if ( is_string($model) )
        {
            $builder = \App::make($model);
        }
        elseif ($model instanceof Model)
        {
            $builder = $model; //$model::select('*')->getQuery();
        }
        else
        {
            return false;
        }

        $this->builderType = 'eloquent';
        $this->init($request, $builder);
    }

    /**
     * Get total count
     *
     * @return $this->totalCount
     */
    public function getTotal()
    {
        return $this->builder->count();
    }

    /**
     * Sort records.
     *
     * @return void
     */
    public function order()
    {
        $this->builder->orderBy($this->sortField, $this->sortOrder);
    }

    /**
     * Paginate records.
     *
     * @return void
     */
    public function paginate()
    {
        $this->builder
            ->take($this->pageSize)
            ->skip($this->pageSize * $this->pageIndex);
    }

    // 表头
    public function getHeader()
    {
        if (! $this->header)
        {
            $this->header =  Schema::getColumnListing( $this->builder->getQuery()->from );
        }
        return $this->header;
    }

    /**
     * 返回数据并缓存
     *
     * @return mixed
     */
    public function getData()
    {
        $result = $this->builder->get();
        if (is_callable($this->transformer)) {
            $result = $result->transform($this->transformer);
        }
        return $result;
    }
}