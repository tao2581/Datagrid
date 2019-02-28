<?php
namespace Tao2581\DataGrid\Engines;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tao2581\DataGrid\Contracts\DataGridEngineContract;
use Prettus\Repository\Helpers\CacheKeys;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Schema;
use App\Contracts\Repositories\Foundation\GridViewRepository;
use App\Models\Foundation\GridView;
use Auth;
use Cache;
abstract class BaseEngine implements DataGridEngineContract
{

    /**
     * Datatables request object.
     *
     * @var \Request
     */
    public $request;
    /**
     * Datagrid database builder
     *
     * @var QueryBuilder
     */
    public $builder;

    /**
     *  builder 类型
     * @var
     */
    public $builderType;
    /**
     * Datagrid total records count
     *
     * @var QueryBuilder
     */
    private $gridName;
    private $totalCount;

    /**
     * Page index default 0
     *
     * @var int
     */
    public $pageIndex;

    /**
     * Page size default 20
     *
     * @var int
     */
    public $pageSize;

    /**
     * Order field default id
     *
     * @var string
     */
    public $sortField;

    /**
     * Order by defult desc
     *
     * @var string
     */
    public $sortOrder;

    /**
     * Filter keyword
     *
     * @var string
     */
    private $keyword;

    /**
     * 需要显示的列 仅能用来过滤header列,不可用于指定查询字段column
     * 因为header可能使用俩个字段 field+display_field
     * @var null
     */
    public $columns= null;
    /**
     * Filter columns key and value
     *
     * @var array()
     */
    public $filters = array();

    /**
     * 模糊搜索列
     *
     * @var array()
     */
    public $globalSearchColumns = [];

    /**
     * @var
     */
    public $cacheRepository;

    /**
     *  视图列表
     * @var
     */
    public $views;

    /**
     *  当前使用视图
     * @var
     */
    public $view;

    /**
     *  默认视图名称
     * @var
     */
    public $defaultViewName;

    /**
     * @var
     */
    public $transformer;
    /**
     * Class Constructor
     *
     * @param Request $request
     */

    /**
     * 表格结果是否使用缓存
     * @var bool
     */
    public $skipCache = false;

    /**
     * @var array
     */
    public $header=[];

    public function __construct(Request $request, $builder)
    {
        $this->init($request, $builder);

    }

    public function init($request, $builder)
    {
        $this->builder      = $builder;
        $this->request      = $request->request->count() ? $request : \Request::instance();
        $this->pageIndex    = $request->pageIndex ? $request->pageIndex : 0;
        $this->pageSize     = $request->pageSize ? $request->pageSize : 20;
        $this->sortField    = $request->sortField ? $request->sortField : 'id';
        $this->sortOrder    = $request->sortOrder ? $request->sortOrder : 'desc';

        $this->keyword      = $request->keyword;
        $this->cacheRepository = $this->getCacheRepository();;
    }


    /**
     * 设置数据表名称,查询数据表视图
     *
     * @param $gridName
     */
    public function setName($gridName)
    {
        $this->gridName = $gridName;

        // 根据名字加载表格设定文件
        $gridDefs = gridefs($gridName);
        if (isset($gridDefs) && is_array($gridDefs))
        {
            $this->loadDefs( $gridDefs );
            // 读取视图列表
            $gridView =\App::make(GridViewRepository::class);
            $gridViews = $gridView->findView(['grid' => $gridName]);
            $this->views = $gridViews;
        }
        return $this;
    }

    public function getViews()
    {
        return $this->views;
    }
    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        // 生效视图数据
        $this->filterView();
        $this->filter();
        $this->totalCount = $this->getTotal();
        if ($this->totalCount) {
            $this->getOrder();
            $this->order($orderFirst);
            $this->paginate();
        }
        return $this->render($mDataSupport);
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
     * 获取过滤参数
     * filter = [{ field:field, operator:operator, value:value }]
     * opeartor: gt,lt,gte,lte,eq,neq,between,like
     */
    public function getOrder()
    {
        // 如果有reuqest排序则替换优先使用 客户端提交时动态的
        if ($this->request->sortField)
            $this->sortField = $this->request->sortField;
        if ($this->request->sortOrder)
            $this->sortOrder = $this->request->sortOrder;

        return $this->filters;
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

    // 表头
    public function getHeader()
    {

        if (! $this->header)
        {
            $this->header =  Schema::getColumnListing( $this->builder->from );
        }
        return $this->header;
    }

    // 表头
    public function getView()
    {
        if ($this->request->view)
        {
            // 如果恢复默认视图
            if ($this->request->view == -1)
            {
                $this->forgetDefaultView();
                $this->defaultViewName = '默认视图';
            }
            else
            {
                $gridVew = \App::make(GridViewRepository::class);
                $gridView =  $gridVew->find($this->request->view);
                if ($gridView)
                    $this->view = $this->getViewFromJson($gridView);
                // 写入缓存
                if (! $this->request->useViewOnce) {
                    $this->setDefaultView($gridView['id']);
                    $this->defaultViewName = $gridView['name'];
                }
            }
        }
        else
        {
            // 查询是否存在设置的默认视图 否则默认配置文件的view
            $cacheKey = $this->getDefaultViewCacheKey();
            if ($cacheKey)
            {
                $defaultViewID = Cache::get($cacheKey);
                if ($defaultViewID)
                {
                    $gridViewRepo = \App::make(GridViewRepository::class);
                    $gridView = $gridViewRepo->find( $defaultViewID );
                    if ($gridView)
                        $this->view = $this->getViewFromJson($gridView);
                    $this->defaultViewName = $gridView['name'];
                }
            }
        }
        return $this->view;
    }

    // 从数据库读出去来的view json 转成view数组
    public function getViewFromJson($viewJson)
    {
        $view['columns'] = json_decode($viewJson['columns'], true);
        $view['filters'] = json_decode($viewJson['filters'], true);
        $view['sortField'] = $viewJson['sortField'];
        $view['sortOrder'] = $viewJson['sortOrder'];
        return $view;
    }

    /**
     * 取消设置的默认视图
     */
    public function forgetDefaultView()
    {
        $cacheKey = $this->getDefaultViewCacheKey();
        Cache::forget($cacheKey);
    }
    // 用户默认视图的Cachekey  = default_view.gridName.user_id
    public function getDefaultViewCacheKey()
    {
        return 'default_view.' . $this->gridName . '.' . Auth::user()->id;
    }
    // 保存用户默认视图
    public function setDefaultView($viewID)
    {
        $cacheKey = $this->getDefaultViewCacheKey();
        Cache::forever($cacheKey, $viewID);
    }

    /**
     * 获取过滤参数
     * filter = [{ field:field, operator:operator, value:value }]
     * 多层过滤条件叠加: 配置文件的过滤设定(角色权限限制) + 视图filter + 来自请求的筛选
     * opeartor: gt,lt,gte,lte,eq,neq,between,like
     */
    public function getFilter()
    {
        if ($this->request->filters){
            $filters = $this->request->filters;
            if (!is_array($filters))
                $filters = json_decode($filters, true);
            $this->filters = array_merge( $filters, $this->filters);
        }
        return $this->filters;
    }

    /**
     * 获取模糊搜索的列 , 如果有ajax请求则覆盖之前设置
     *
     * @return array
     */
    public function getGlobalSearchColumns()
    {
        if ($this->request->globalSearchColumns){
            $this->globalSearchColumns = $this->request->globalSearchColumns;
        }
        return $this->globalSearchColumns;
    }

    /**
     * @return array
     */
    public function getFilterOperators($param)
    {
        $operators= [
          'gt'  => '>',
          'gte' => '>=',
          'lt'  => '<',
          'lte' => '<=',
          'like' => 'like',
          'eq' => '=',
        ];
        if ($operators[$param])
            return $operators[$param];
        else
            return false;
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

    /**
     * Filter records.
     *
     * @return void
     */
    public function filter()
    {
        if ( !isset($this->filters) )
        $this->filters = $this->getFilter();
        if (! empty($this->filters))
        {
            $this->builder->where(
                function ($query) {
                    //$keyword = $this->setupKeyword($this->keyword());
                    foreach ($this->filters as $columnName => $columnValue) {
                        $columnValue=trim($columnValue);
                        if (isset($columnValue)) {
                            $query->where($columnName, 'like', '%'. $columnValue .'%');
                        } else {
                            //$this->compileGlobalSearch($this->getQueryBuilder($query), $columnName, $keyword);
                        }
                    }
                }
            );
        }

    }
    /**
     * Return instance of Cache Repository
     *
     * @return CacheRepository
     */
    public function getCacheRepository()
    {
        if ( is_null($this->cacheRepository) ) {
            $this->cacheRepository = app( config('repository.cache.repository','cache') );
        }

        return $this->cacheRepository;
    }

    /**
     * Set data output transformer.
     *
     * @param \League\Fractal\TransformerAbstract $transformer
     * @return $this
     */
    public function setTransformer($transformer)
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * 设置过滤器
     *
     * @param $filters
     * @return $this
     */
    public function setFilter($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * 设置全局搜索列
     *
     * @param array $columns
     * @return $this
     */
    public function setGlobalSearchColumns($columns)
    {
        $this->globalSearchColumns = $columns;

        return $this;
    }

    /**
     * 设置排序
     *
     * @param $sortField
     * @param string $sortOrder
     * @return $this
     */
    public function setOrderby($sortField, $sortOrder = 'desc')
    {
        $this->sortField = $sortField;
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * 设置列
     *
     * @param $columns
     * @return $this
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        $this->setHeaderByColumns();

        return $this;
    }

    /**
     * Set data output columns.
     *
     * @param $header
     * @return $this
     */
    public function setHeader($header)
    {
        $this->header = $header;
        $this->setHeaderByColumns();
        return $this;
    }

    /**
     * 依据columns筛选表头, 依据header完善columns
     * @return $this
     */
    private function setHeaderByColumns()
    {
        if ( isset($this->columns) && count( $this->columns )>1 )
        {
            $columns = collect($this->columns);
            $newHeader = [];
            $newColumns = [];
            foreach($this->columns as $column)
            {
                foreach($this->header as $header)
                {
                    // 如果存在displayField 则以此为列名匹配
                    if (array_key_exists('displayField',$header))
                    {
                        $headerColumn = $header['displayField'];
                    }
                    else
                        $headerColumn = $header['field'];

                    if ( $column == $headerColumn )
                    {
                        $newHeader[] = $header;
                        $newColumns[] = $column;
                        if (array_key_exists('displayField',$header))
                            $newColumns[] = $header['displayField'];
                    }
                }
            }
            $this->header = $newHeader;
            $this->columns = $newColumns;
        }
        return $this;
    }

    public function setView($view)
    {
        $this->view = $view;
    }

    public function filterView()
    {
        $view = $this->getView();

        // 设置列
        if (isset($view['columns']))
            $this->setColumns($view['columns']);

        if (isset($view['sortField']))
            $this->sortField = $view['sortField'];
        if (isset($view['sortOrder']))
            $this->sortOrder = $view['sortOrder'];
        if (isset($view['filters']))
            $this->setFilter($view['filters']);
        return $this;
    }

    /**
     * 返回数据并缓存
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->builder->get( $this->columns );
    }

    /**
     * 加载数据表设定文件
     * @param array $defs
     * @return $this
     */
    public function loadDefs(array $defs)
    {
        if ( array_key_exists('header',$defs) )
            $this->header = $defs['header'];

        if ( array_key_exists('default_view',$defs) )
            $this->setView($defs['default_view']);

        if ( array_key_exists('globalSearchColumns',$defs) )
            $this->setGlobalSearchColumns($defs['globalSearchColumns']);

        if ( array_key_exists('transformer',$defs) )
            $this->setTransformer($defs['transformer']);

        return $this;
    }

    /**
     * 获取eloquent模型关联表.
     *
     * @return array
     */
    public function getEagerLoads()
    {
        if ($this->builderType == 'eloquent') {
            return array_keys($this->builder->getEagerLoads());
        }

        if ($this->builderType == 'repository') {
            return array_keys($this->builder->getModel()->getEagerLoads());
        }

        return [];
    }

    public function skipCache($status = true)
    {
        $this->skipCache = $status;
        return $this;
    }

    /**
     * Render view.
     *
     * @param $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function render($view, $data = [], $mergeData = [])
    {
        if ($this->request->ajax() && $this->request->wantsJson()) {
            return $this->json();
        }

        switch ($this->request->get('action')) {
            case 'excel':
                return $this->excel();

            case 'csv':
                return $this->csv();

            case 'pdf':
                return $this->pdf();

            case 'print':
                return $this->printPreview();

            default:
                return $this->json();
        }
    }

    public function json()
    {
        $output = array(
            'total'     =>  $this->totalCount,
            'data'      =>  $this->getData(),
            'header'    =>  $this->getHeader(),
            'views'    =>  $this->getViews(),
            'gridName'    =>  $this->gridName,
            'defaultViewName'    =>  $this->defaultViewName,
        );

        return new JsonResponse($output);
    }
}
