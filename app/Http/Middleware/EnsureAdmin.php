<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // ログインしていない場合は、ログイン画面へ移動する
        if (! $request->user()) {
            return redirect()->route('admin.login');
        }

        // ログインしていても、管理者でなければアクセスさせない
        if (! $request->user()->admin_status) {
            abort(403);
        }

        // 管理者なら、次の処理へ進む
        return $next($request);
    }
}
