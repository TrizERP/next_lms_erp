<?php

namespace App\Vendor\Jwt\Support;

use Illuminate\Support\ServiceProvider;

class LaravelServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            base_path('config/jwt.php') => config_path('jwt.php'),
        ], 'jwt-config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            base_path('config/jwt.php'), 'jwt'
        );
        
        $this->app->singleton(\App\Vendor\Jwt\JwtToken::class, function ($app) {
            return new \App\Vendor\Jwt\JwtToken();
        });
    }
}
