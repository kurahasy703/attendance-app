<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function store(Request $request)
    {
        // ① 入力内容をチェック
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // ② メールアドレスとパスワードを確認
        if (!Auth::attempt($credentials)) {
            return back()
                ->withErrors([
                    'email' => 'メールアドレスまたはパスワードが正しくありません。',
                ])
                ->withInput($request->only('email'));
        }

        // ③ 管理者でなければログインを取り消す
        if (!Auth::user()->admin_status) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => '管理者権限がありません。',
                ])
                ->withInput($request->only('email'));
        }

        // ④ ログイン成功後、セッションを更新
        $request->session()->regenerate();

        // ⑤ 管理者の勤怠一覧画面へ移動
        return redirect()->route('admin.attendance.list');
    }
}
