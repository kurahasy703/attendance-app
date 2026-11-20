<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use App\Http\Controllers\AttendanceReportController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AttendanceExportController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use Illuminate\Http\Request;


// トップページ（/）にアクセスした場合は打刻画面へリダイレクト
Route::get('/', function () {
    return redirect()->route('attendance.index');
});

// ==========================================
// 認証前のルート（ログイン・会員登録画面）
// ==========================================
// PG01: 会員登録画面（一般ユーザー）
Route::get('/register', [RegisteredUserController::class, 'create'])
    ->middleware(['guest'])
    ->name('register');

// PG02: ログイン画面（一般ユーザー）
Route::get('/login', function () {
    return view('user.user-login');
})->middleware(['guest'])->name('login');

// PG07: ログイン画面（管理者）
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware(['guest'])->name('admin.login');
Route::post('/admin/login', [AdminLoginController::class, 'store'])
    ->middleware(['guest'])
    ->name('admin.login.store');
// 管理者ログアウト（Fortifyの既存処理を利用）
Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'admin'])
    ->name('admin.logout');
// ==========================================
// 認証後のルート（ログイン必須グループ）
// ==========================================
Route::middleware(['auth'])->group(
    function () {

        // PG03：打刻画面表示・打刻処理
        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->name('attendance.index');

        Route::post('/attendance', [AttendanceController::class, 'stamp'])
            ->name('attendance.stamp');

        // 管理者：スタッフ一覧
        Route::get('/admin/staff/list', [AdminAttendanceController::class, 'staffList'])
            ->middleware(['admin'])
            ->name('admin.staff.list');

        // PG04：一般ユーザーの勤怠一覧
        Route::get('/attendance/list', [AttendanceController::class, 'list'])
            ->name('attendance.list');

        // PG05：一般ユーザーの勤怠詳細
        Route::get('/attendance/detail/{id}', [AttendanceController::class, 'show'])
            ->name('attendance.detail');

        // PG14: マイ勤怠レポート画面
        Route::get('/attendance/report', [AttendanceReportController::class, 'index'])->name('[不審なリンクを削除しました]');

        // 勤怠詳細画面
        Route::get('/attendance/{id}', function ($id) {
            if (auth()->user()->admin_status) {
                return redirect()->route('admin.attendance.detail', ['id' => $id]);
            }

            return app(AttendanceController::class)->show($id);
        });

        // 一般ユーザー：修正申請の詳細へ移動
        Route::get('/application/{id}', function ($id) {
            $application = \App\Models\StampCorrectionRequest::with('attendanceRecord')
                ->findOrFail($id);

            // 他のユーザーの申請は表示させない
            abort_unless(
                $application->attendanceRecord->user_id === auth()->id(),
                403
            );

            return redirect()->route('attendance.detail', [
                'id' => $application->attendance_record_id,
            ]);
        })->name('application.detail');



        Route::post('/attendance/{id}', function (Request $request, $id) {

            // 管理者の場合は、勤怠データを直接更新する
            if ($request->user()->admin_status) {
                return app(AdminAttendanceController::class)->update($request, $id);
            }

            // 一般ユーザーの場合は、従来どおり修正申請を作成する
            return app(AttendanceController::class)->storeCorrectionRequest($request, $id);
        });

        Route::post('/stamp_correction_request/store', [StampCorrectionRequestController::class, 'store'])->name('[不審なリンクを削除しました]');

        // PG06: 申請一覧画面（一般）
        Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'list'])->name('stamp_correction_request.list');



        // PG08：管理者の勤怠一覧画面
        Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])
            ->middleware(['admin'])
            ->name('admin.attendance.list');
        // 管理者の勤怠詳細画面
        Route::get('/admin/attendance/detail/{id}', [AdminAttendanceController::class, 'show'])
            ->middleware(['admin'])
            ->name('admin.attendance.detail');

        // 管理者：スタッフ別月次勤怠一覧
        Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendanceList'])
            ->middleware(['admin'])
            ->name('admin.staff.attendance.list');

        // 管理者：スタッフ別月次勤怠CSV出力
        Route::post('/export', [AttendanceExportController::class, 'export'])
            ->middleware(['admin'])
            ->name('admin.attendance.export');
        // 管理者：修正申請一覧
        Route::get('/admin/stamp_correction_request/list', [AdminStampCorrectionRequestController::class, 'index'])
            ->middleware(['admin'])
            ->name('admin.stamp_correction_request.list');
        // 管理者：修正申請詳細
        Route::get('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'show'])
            ->middleware(['admin'])
            ->name('admin.stamp_correction_request.show');
        // 管理者：修正申請を承認する
        Route::post('/stamp_correction_request/approve/{id}', [AdminStampCorrectionRequestController::class, 'approve'])
            ->middleware(['admin'])
            ->name('admin.stamp_correction_request.approve');
    }
);
