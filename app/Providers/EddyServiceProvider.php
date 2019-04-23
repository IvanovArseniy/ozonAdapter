<?php

namespace App\Providers;

use App\Services\EddyService;
use Illuminate\Support\ServiceProvider;

class EddyServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('App\Services\EddyService', function ($app) {
            return new EddyService();
        });
    }
}
