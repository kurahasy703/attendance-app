<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * 未認証時にユーザーをリダイレクトするパスを取得
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // URLが /admin から始まる場合は管理者ログイン画面（admin.login）へ
        if ($request->is('admin*')) {
            return route('admin.login');
        }

        // それ以外は一般ユーザーログイン画面（login）へ
        return route('login');
    }
}
