<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Models\AttendanceRecord;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;

class AttendanceRecordController extends Controller
{
    // 勤怠一覧を取得する
    public function index(IndexAttendanceRecordRequest $request)
    {
        // 入力チェック済みの検索条件を取得
        $filters = $request->validated();

        // 1ページの表示件数（指定がなければ20件）
        $perPage = $filters['per_page'] ?? 20;

        // 勤怠情報と関連するユーザー・休憩情報を取得
        $query = AttendanceRecord::with(['user', 'breaks']);

        // ユーザーIDで絞り込み
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // 日付で絞り込み
        if (isset($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }

        // 年月で絞り込み
        if (isset($filters['month'])) {
            $query->where('date', 'like', $filters['month'] . '%');
        }

        // 日付の新しい順に並べ、ページ単位で取得
        $attendanceRecords = $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    // 指定された勤怠記録の詳細を取得する
    public function show(AttendanceRecord $attendanceRecord)
    {
        // 関連するユーザー・休憩・修正申請を読み込む
        $attendanceRecord->load([
            'user',
            'breaks',
            'stampCorrectionRequests',
        ]);

        // API用の形式で返す
        return new AttendanceRecordResource($attendanceRecord);
    }

    // AP03：新しい勤怠記録を登録する
    public function store(StoreAttendanceRecordRequest $request)
    {
        // 入力チェックを通過したデータを取得
        $validated = $request->validated();

        // 認証済みユーザーの勤怠として登録
        $attendanceRecord = $request->user()
            ->attendanceRecords()
            ->create($validated);

        // ユーザーと休憩情報を読み込む
        $attendanceRecord->load(['user', 'breaks']);

        // 登録した勤怠情報をHTTP 201で返す
        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }
}
