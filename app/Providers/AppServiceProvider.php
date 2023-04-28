<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrap();

        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }

        Schema::defaultStringLength(191);
        DB::listen(function ($query) {

            $query_arr = explode(' ', $query->sql);
            $search_array = array_map('strtolower', $query_arr);

            if (!in_array('select', $search_array) && !in_array('`access_log`', $search_array)) {
//                DB::table('access_log')->insert([
//                    'SYEAR' => session()->get('syear'),
//                    'CURRUNT_URL' => url()->current(),
//                    'CURRUNT_ROUTE' => \Route::current()->getName(),
//                    'QUERY' => $query->sql,
//                    'BINDINGS' => json_encode($query->bindings),
//                    'USER_ID' => session()->get('user_id'),
//                    'IP' => \Request::getClientIp(),
//                    'SUB_INSTITUTE_ID' => session()->get('sub_institute_id')
//                ]);
            }
        });
    }
}
