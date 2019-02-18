<?php

namespace App\Providers;

use App\Services\OzonService;
use Illuminate\Support\ServiceProvider;

class OzonServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('App\Services\OzonService', function ($app) {
            return new OzonService();
        });
    }
}
