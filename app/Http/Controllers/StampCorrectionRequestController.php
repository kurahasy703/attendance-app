<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use Illuminate\Support\Facades\Auth;

class StampCorrectionRequestController extends Controller
{
    /**
     * 修正申請の送信処理（PG05 勤怠詳細画面からのリクエスト）
     */
    public function store(Request $request)
    {
        // 1. バリデーション（要件定義に基づくエラーメッセージを設定）
        $request->validate([
            'attendance_record_id' => 'required|exists:attendance_records,id',
            'clock_in' => 'required|date_format:H:i',
            'clock_out' => 'required|date_format:H:i|after:clock_in',
            'comment' => 'required|string|max:255',
            'breaks' => 'nullable|array',
            'breaks.*.break_in' => 'required_with:breaks.*.break_out',
            'breaks.*.break_out' => 'required_with:breaks.*.break_in|after:breaks.*.break_in',
        ], [
            'comment.required' => '備考を記入してください',
            'clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'breaks.*.break_out.after' => '休憩時間が不適切な値です',
        ]);

        // 2. 申請データ（親）の作成
        $correctionRequest = StampCorrectionRequest::create([
            'attendance_record_id' => $request->attendance_record_id,
            'status' => 'pending', // 初期状態は「承認待ち」
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
            'comment' => $request->comment,
        ]);

        // 3. 申請用休憩データ（子）の作成（入力がある場合）
        if ($request->has('breaks')) {
            foreach ($request->breaks as $breakData) {
                if (!empty($breakData['break_in']) && !empty($breakData['break_out'])) {
                    StampCorrectionRequestBreak::create([
                        'stamp_correction_request_id' => $correctionRequest->id,
                        'break_in' => $breakData['break_in'],
                        'break_out' => $breakData['break_out'],
                    ]);
                }
            }
        }

        // 申請完了後、申請一覧画面へリダイレクト
        return redirect()->route('stamp_correction_request.list')
            ->with('message', '修正申請を送信しました。');
    }

    /**
     * PG06 / PG12: 申請一覧画面の表示
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'pending'); // デフォルトは「承認待ち」タブ

        // クエリ構築（勤怠記録・ユーザー情報をEager Loading）
        $query = StampCorrectionRequest::with(['attendanceRecord.user', 'attendanceRecord']);

        // 一般ユーザーの場合は「自分の勤怠に紐づく申請のみ」に絞り込み
        if ($user->admin_status !== 1) {
            $query->whereHas('attendanceRecord', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        }

        // タブに応じたステータス絞り込み
        if ($tab === 'approved') {
            $query->where('status', 'approved');
        } else {
            $query->where('status', 'pending');
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return view('stamp_correction_request.list', compact('requests', 'tab'));
    }
}
