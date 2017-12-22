<?php
namespace Tao2581\Datagrid\DataGrid\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tao2581\Datagrid\DataGrid\Contracts\DataGridEngineContract;
use Prettus\Repository\Contracts\RepositoryInterface;
use Schema;
use Prettus\Repository\Helpers\CacheKeys;

class RepositoryEngine extends BaseEngine implements DataGridEngineContract
{

    private $repository;
    /**
     * @param mixed $model
     * @param Request $request
     */
    public function __construct(Request $request, $repository)
    {
        if ($repository instanceof RepositoryInterface)
        {
            $builder =$repository;// $repoModel::select('*')->getQuery();
        }
        else
        {
            return false;
        }
        $this->repository = $repository;
        $this->builderType = 'repository';
        $this->init($request, $builder);

        //如果存在transform 设置
//        if (method_exists($repoModel, 'transform'))
//        {
//            $this->setTransformer($repoModel->transform());
//
//        }
//
//        //如果存在列设置
//        if ( isset( $this->repository->gridHeader) )
//        {
//            $this->setHeader( $this->repository->gridHeader );
//        }

    }

    /**
     * 返回数据并缓存
     * TODO: 如果是搜索 则不缓存数据 缓存此类临时数据无意义
     *
     * @return mixed
     */
    public function getData()
    {
        // 如果已指明不需缓存
        if ($this->skipCache)
        {
            $result = $this->builder->skipCache()->get();
            if (is_callable($this->transformer) && $result instanceof Collection){
                $result = $result->transform($this->transformer);
            }
            return $result;
        }
        else
        {
            $key = $this->repository->getCacheKey('datagrid-data', $this->builder->getUniqueKey());
            // key写入 以便于attr更新修改后清除key
            CacheKeys::putKey(get_class($this->builder), $key);
            $minutes = 60*72;
            $value   = $this->cacheRepository->remember($key, $minutes, function() {
                // TODO:当前columns不准无法使用 $data = $builder->get( $this->columns );
                $data = $this->builder->get();
                if (is_callable($this->transformer) && $data instanceof Collection){
                    $data = $data->transform($this->transformer);
                }
                if ( $data instanceof Collection )
                    return $data;
                else
                    return $data;
            });
            return $value;
        }
    }

    /**
     * 获取记录数量并缓存
     *
     * @return $this->totalCount
     */
    public function getTotal()
    {
        // 如果已指明不需缓存
        if ($this->skipCache)
        {
            return $this->builder->skipCache()->count();
        }
        else
        {
            $key     = $this->repository->getCacheKey('datagrid-count', $this->builder->getUniqueKey());
            $minutes = 60*72;
            $value   = $this->cacheRepository->remember($key, $minutes, function() {
                return $this->builder->count();
            });
            return $value;
        }

    }

    /**
     * Sort records.
     *
     * @return void
     */
    public function order()
    {
        $this->builder->scopeQuerys(function($query){
            return $query->orderBy($this->sortField,$this->sortOrder);
        });
    }

    /**
     * Paginate records.
     *
     * @return void
     */
    public function paginate()
    {
        $this->builder->scopeQuerys(function($query){
            return $query->take($this->pageSize)
                ->skip($this->pageSize * $this->pageIndex);
        });

    }

    public function getHeader()
    {
        $header = [];
        if (! $this->header)
        {
            $header =  Schema::getColumnListing( $this->builder->getModel()->getQuery()->from );
            foreach($header as $column)
            {
                if (! in_array($column, ['id','created_at','updated_at']) )
                    $this->header[] = $column;
            }
        }
        return $this->header;
    }

    /**
     * Filter records.
     *
     * @return void
     */
    public function filter()
    {
        $filters = $this->getFilter();
        $globalSearchColumns = $this->getGlobalSearchColumns();

        $this->builder->scopeQuerys(function($query) use($filters, $globalSearchColumns) {
            return $query->where(
                function ($query) use($filters, $globalSearchColumns) {

                    // 列筛选
                    if (! empty($filters))
                    {
                        //$keyword = $this->setupKeyword($this->keyword());
                        foreach ($filters as $filter) {
                            if(! array_key_exists("operator",$filter))
                                $operator = 'like';
                            else
                            {
                                $operator = $this->getFilterOperators($filter['operator']);
                                if(!$operator) $operator='like';
                            }
                            $operator = $operator ? $operator : '';
                            if (array_key_exists("value",$filter) ) {
                                if ($operator == 'like' )
                                    $filter['value'] = "'%".$filter['value']."%'";
                                // 如果是关联表字段查询
                                if (count(explode('.', $filter['field'])) > 1) {
                                    $this->compileRelationFilter($query, $filter['field'], $filter['value'], $operator );
                                }
                                else
                                {
                                    if ($operator != 'like' )
                                        $query->where($filter['field'], $operator,  $filter['value'] );
                                    else
                                        $query->whereRaw($filter['field']." like ".$filter['value']);
                                }

                            } else {
                                //$this->compileGlobalSearch($this->getQueryBuilder($query), $columnName, $keyword);
                            }
                        }
                    }
                    $eagerLoads     = $this->getEagerLoads();
                    // 关键词模糊查询
                    $keyword = $this->request->keyword;
                    if (! empty($globalSearchColumns) && ! empty($keyword) )
                    {
                        $query->Where(function($query) use($globalSearchColumns, $keyword){

                            foreach ($globalSearchColumns as $column) {

                                if (count(explode('.', $column)) > 1) {
                                    $this->compileRelationSearch($query, $column, $keyword);
                                }
                                else {
                                    $query->orWhere($column, 'like',  "%".$keyword."%" );
                                }
                            }

                        });
                    }


                }
            );
        });


    }

    /**
     * 关联表搜索
     *
     * @param $query
     * @param $columnName
     * @param $keyword
     */
    public function compileRelationSearch($query, $columnName, $keyword)
    {
        $eagerLoads     = $this->getEagerLoads();
        $parts          = explode('.', $columnName);
        //指明leads.name 否则报错 $relationColumn = array_pop($parts);
        $relation       = array_shift($parts);
        // $eagerLoads取不到,暂时不用
        if (in_array($relation, $eagerLoads) || 1==1) {
            $query->orwhereHas($relation, function($q) use($columnName, $keyword) {
                $q->where($columnName, 'like', "'%" . $keyword . "%'");
            });
        }
    }

    /**
     * 关联表查询
     *
     * @param $query
     * @param $columnName
     * @param $value
     * @param $operator
     */
    public function compileRelationFilter($query, $columnName, $value, $operator)
    {
        $eagerLoads = $this->getEagerLoads();
        $parts = explode('.', $columnName);
        //指明leads.name 否则报错 $relationColumn = array_pop($parts);
        $relation = array_shift($parts);
        $relationColumn = implode('.', $parts);
        // $eagerLoads取不到,暂时不用
        if (in_array($relation, $eagerLoads) || 1==1) {
            $query->whereHas($relation, function($q) use($relationColumn, $operator, $value) {
                $q->whereRaw(" $relationColumn like $value" );
            });
        }
    }


}