<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * PG03: 打刻画面表示＆ステータス判定
     */
    public function index()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();

        // 本日の勤怠記録を取得
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->first();

        // 現在の画面ステータス判定
        // 0: 勤務外, 1: 出勤中, 2: 休憩中, 3: 退勤済
        $status = 0;

        if ($attendance) {
            if ($attendance->clock_out) {
                $status = 3; // 退勤済
            } else {
                // 開いている（休憩戻りがまだの）休憩記録があるかチェック
                $openBreak = BreakTime::where('attendance_record_id', $attendance->id)
                    ->whereNull('break_out')
                    ->first();

                if ($openBreak) {
                    $status = 2; // 休憩中
                } else {
                    $status = 1; // 出勤中
                }
            }
        }

        return view('attendance.index', compact('attendance', 'status'));
    }

    /**
     * 出勤打刻処理
     */
    public function clockIn()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        // 本日の重複打刻を防止
        $exists = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->exists();

        if (!$exists) {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => $today,
                'clock_in' => $now,
            ]);
        }

        return redirect()->route('attendance.index');
    }

    /**
     * 退勤打刻処理
     */
    public function clockOut()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if ($attendance) {
            $attendance->update([
                'clock_out' => $now,
            ]);
        }

        return redirect()->route('attendance.index');
    }

    /**
     * 休憩入打刻処理
     */
    public function breakIn()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if ($attendance) {
            BreakTime::create([
                'attendance_record_id' => $attendance->id,
                'break_in' => $now,
            ]);
        }

        return redirect()->route('attendance.index');
    }

    /**
     * 休憩戻打刻処理
     */
    public function breakOut()
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', $today)
            ->whereNull('clock_out')
            ->first();

        if ($attendance) {
            $openBreak = BreakTime::where('attendance_record_id', $attendance->id)
                ->whereNull('break_out')
                ->first();

            if ($openBreak) {
                $openBreak->update([
                    'break_out' => $now,
                ]);
            }
        }

        return redirect()->route('attendance.index');
    }

    /**
     * PG04: 勤怠一覧画面表示
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        // 当月を取得（指定がない場合は今月）
        $month = $request->input('month', Carbon::now()->format('Y-m'));

        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->where('date', 'like', "{$month}%")
            ->with('breaks')
            ->orderBy('date', 'asc')
            ->get();

        return view('attendance.list', compact('attendances', 'month'));
    }

    /**
     * PG05: 勤怠詳細画面表示
     */
    public function show($id)
    {
        $attendance = AttendanceRecord::with(['user', 'breaks', 'stampCorrectionRequests'])->findOrFail($id);

        return view('[不審なリンクを削除しました]', compact('attendance'));
    }
}
