<?php
namespace Tao2581\DataGrid\DataGrid;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider {

    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Tao2581\DataGrid\Events\RepositoryEntityCreated' => [
            'Prettus\Repository\Listeners\CleanCacheRepository'
        ],
        'Tao2581\DataGrid\Events\RepositoryEntityUpdated' => [
            'Prettus\Repository\Listeners\CleanCacheRepository'
        ],
        'Tao2581\DataGrid\Events\RepositoryEntityDeleted' => [
            'Prettus\Repository\Listeners\CleanCacheRepository'
        ]
    ];
}