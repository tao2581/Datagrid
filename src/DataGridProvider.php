<?php

namespace Tao2581\DataGrid;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

class DataGridProvider extends ServiceProvider
{
    protected $defer = false;

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {

        // 绑定
        $this->app->bind(
            \Tao2581\DataGrid\Facades\DataGridFacade::class,
            \Tao2581\DataGrid\DataGridService::class
        );
    }

}