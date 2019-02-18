<?php

namespace App\Providers;

use App\Services\DropshippService;
use Illuminate\Support\ServiceProvider;

class DropshippServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('App\Services\DropshippService', function ($app) {
            return new DropshippService();
        });
    }
}
