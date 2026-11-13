<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }


    public function boot(): void
    {
        // 全てのビューにログインユーザー情報を共有
        View::composer('*', function ($view) {
            $view->with('authUser', Auth::user());
        });
    }
}
