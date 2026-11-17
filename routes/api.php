<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AttendanceRecordController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// AP01：勤怠一覧取得API
Route::get('/v1/attendance-records', [
    AttendanceRecordController::class,
    'index'
]);

// AP02：勤怠詳細取得API
Route::get('/v1/attendance-records/{attendanceRecord}', [
    AttendanceRecordController::class,
    'show'
])->name('api.attendance-records.show');

// AP06：認証が必要なAPI
Route::middleware('auth:sanctum')->group(function () {

    // AP03：勤怠新規登録API
    Route::post('/v1/attendance-records', [
        AttendanceRecordController::class,
        'store'
    ]);

    // AP04：勤怠更新API
    Route::match(['put', 'patch'], '/v1/attendance-records/{attendanceRecord}', [
        AttendanceRecordController::class,
        'update'
    ]);

    // AP05：勤怠削除API
    Route::delete('/v1/attendance-records/{attendanceRecord}', [
        AttendanceRecordController::class,
        'destroy'
    ]);
});
