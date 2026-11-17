<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});



Route::get('/v1/attendance-records', [
    \App\Http\Controllers\Api\V1\AttendanceRecordController::class,
    'index'
]);

Route::get('/v1/attendance-records/{attendanceRecord}', [
    \App\Http\Controllers\Api\V1\AttendanceRecordController::class,
    'show'
])
    ->name('api.attendance-records.show')
    ->missing(function () {
        return response()->json([
            'error' => '勤怠情報が見つかりませんでした。',
        ], 404);
    });

Route::middleware('auth:sanctum')->post(
    '/v1/attendance-records',
    [
        \App\Http\Controllers\Api\V1\AttendanceRecordController::class,
        'store'
    ]
);
// AP04：勤怠更新（認証必須）
Route::middleware('auth:sanctum')->match(
    ['put', 'patch'],
    '/v1/attendance-records/{attendanceRecord}',
    [
        \App\Http\Controllers\Api\V1\AttendanceRecordController::class,
        'update'
    ]
);
// AP05：勤怠削除API
Route::middleware('auth:sanctum')
    ->delete('/v1/attendance-records/{attendanceRecord}', [
        \App\Http\Controllers\Api\V1\AttendanceRecordController::class,
        'destroy'
    ])
    ->missing(function () {
        return response()->json([
            'error' => '勤怠情報が見つかりませんでした。'
        ], 404);
    });
