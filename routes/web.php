<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;

// トップページ（/）にアクセスした場合は打刻画面へリダイレクト
Route::get('/', function () {
    return redirect()->route('attendance.index');
});

// 認証済みユーザー用グループ
Route::middleware(['auth'])->group(function () {
    // 1. 打刻画面（PG03）表示・各種打刻アクション
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
    Route::post('/attendance/break-in', [AttendanceController::class, 'breakIn'])->name('attendance.breakIn');
    Route::post('/attendance/break-out', [AttendanceController::class, 'breakOut'])->name('attendance.breakOut');

    // 2. 勤怠一覧画面（PG04）
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');

    // 3. 勤怠詳細画面（PG05）＆ 修正申請送信
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('[不審なリンクを削除しました]');
    Route::post('/stamp_correction_request/store', [StampCorrectionRequestController::class, 'store'])->name('[不審なリンクを削除しました]');

    // 4. 申請一覧画面（PG06 / PG12 共通）
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'list'])->name('stamp_correction_request.list');
});
