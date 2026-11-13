<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use App\Models\AttendanceRecord;

class StampCorrectionRequestController extends Controller
{
    /**
     * PG06: 申請一覧画面の表示 (GET /stamp_correction_request/list)
     */

    public function list(Request $request)
    {
        // ログイン中の一般ユーザーを取得
        $user = Auth::user();

        // 本人の修正申請をすべて取得
        $requests = StampCorrectionRequest::whereHas(
            'attendanceRecord',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )
            ->with('attendanceRecord')
            ->orderBy('created_at', 'desc')
            ->get();

        // Bladeで表示できる形式に変換
        $requests = $requests->map(function ($application) {
            return [
                'id' => $application->id,

                'approval_status' =>
                $application->status === 'approved'
                    ? '承認済み'
                    : '承認待ち',

                'date' => \Carbon\Carbon::parse(
                    $application->attendanceRecord->date
                )->format('Y/m/d'),

                'comment' => $application->comment,

                'application_date' =>
                $application->created_at->format('Y/m/d'),
            ];
        });

        return view('user.user-application-list', compact('requests', 'user'));
    }


    /**
     * PG05: 勤怠詳細画面からの修正申請送信処理 (POST /stamp_correction_request/store)
     */
    public function store(Request $request)
    {
        // 1. バリデーション
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
        // 2. ログインユーザー本人の勤怠記録を取得
        $attendance = AttendanceRecord::where('user_id', Auth::id())
            ->findOrFail($request->attendance_record_id);

        // 2. 修正申請の作成
        $correctionRequest = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'status' => 'pending',
            'date' => $attendance->date,
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
            'comment' => $request->comment,
        ]);

        // 3. 申請用休憩データの作成
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

        return redirect()->route('stamp_correction_request.list')
            ->with('message', '修正申請を送信しました。');
    }
}
