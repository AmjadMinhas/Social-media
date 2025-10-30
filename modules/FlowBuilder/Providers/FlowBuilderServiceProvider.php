<?php

namespace Modules\FlowBuilder\Providers;

use Illuminate\Support\ServiceProvider;


class FlowBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}