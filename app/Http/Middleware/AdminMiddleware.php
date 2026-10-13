<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ログインしていない、または管理者フラグが0（一般ユーザー）の場合
        if (!Auth::check() || Auth::user()->admin_status !== 1) {
            // 403 Forbidden エラーを返す（または適切な画面へリダイレクト）
            abort(403, '管理者権限が必要です。');
        }

        return $next($request);
    }
}
