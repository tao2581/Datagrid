<?php

namespace Tao2581\Datagrid\DataGrid\Contracts;

interface DataGridEngineContract
{

    public function init($request, $builder);

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false);

    /**
     * Get total count
     *
     * @return $this->totalCount
     */
    public function getTotal();

    /**
     * Sort records.
     *
     * @return void
     */
    public function order();

    // 表头
    public function getHeader();

    /**
     * Paginate records.
     *
     * @return void
     */
    public function paginate();

    /**
     * Filter records.
     *
     * @return void
     */
    public function filter();

    /**
     * Render view.
     *
     * @param $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function render($view, $data = [], $mergeData = []);

    public function json();
}
