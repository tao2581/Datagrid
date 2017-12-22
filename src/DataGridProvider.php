<?php

namespace Tao2581\DataGrid;

use Illuminate\Http\Request;
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
        $this->app->singleton('DataGridService', function ($app) {
            return new DataGridService($app->make(Request::class));
        });
    }
}