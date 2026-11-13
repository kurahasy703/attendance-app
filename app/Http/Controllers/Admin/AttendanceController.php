<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    // 管理者の勤怠一覧画面を表示する
    public function index(Request $request)
    {
        // URLで日付が指定されていればその日、なければ今日
        $date = Carbon::parse($request->input('date', today()->toDateString()));

        // 前日と翌日の日付
        $previousDay = $date->copy()->subDay()->toDateString();
        $nextDay = $date->copy()->addDay()->toDateString();

        // 管理者を除いたユーザー一覧
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        // 指定した日の勤怠記録を取得
        $attendanceRecords = AttendanceRecord::whereDate('date', $date->toDateString())
            ->get();

        // 取得したデータを画面に渡す
        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    // 管理者の勤怠詳細画面を表示する
    public function show($id)
    {
        // 指定された勤怠記録と、スタッフ情報・休憩記録を取得する
        $attendance = AttendanceRecord::with(['user', 'breaks'])
            ->findOrFail($id);

        // 休憩時間を画面表示用に整える
        $formattedBreaks = $attendance->breaks->map(function ($break) {
            return [
                'break_in' => $break->break_in
                    ? Carbon::parse($break->break_in)->format('H:i')
                    : '',
                'break_out' => $break->break_out
                    ? Carbon::parse($break->break_out)->format('H:i')
                    : '',
            ];
        })->values()->all();

        // 詳細画面に渡すデータをまとめる
        $attendanceRecord = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('m月d日'),
            'clock_in' => $attendance->clock_in
                ? Carbon::parse($attendance->clock_in)->format('H:i')
                : '',
            'clock_out' => $attendance->clock_out
                ? Carbon::parse($attendance->clock_out)->format('H:i')
                : '',
            'breaks' => $formattedBreaks,
            'comment' => $attendance->comment ?? '',
        ];

        // スタッフ情報を取得する
        $user = $attendance->user;

        // 管理者用の勤怠詳細画面を表示する
        return view('admin.admin-detail', compact(
            'attendanceRecord',
            'user'
        ));
    }

    // 管理者のスタッフ一覧画面を表示する
    public function staffList()
    {
        // 管理者以外のスタッフを取得する
        $users = User::where('admin_status', false)
            ->orderBy('id')
            ->get();

        // スタッフ一覧画面にデータを渡す
        return view('admin.staff-list', compact('users'));
    }

    // 管理者：スタッフ別月次勤怠一覧
    public function staffAttendanceList(Request $request, $id)
    {
        // 選択されたスタッフを取得
        $user = User::where('admin_status', false)
            ->findOrFail($id);

        // 表示する年月
        $monthParam = $request->input('date', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('!Y-m', $monthParam);

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 選択したスタッフの1か月分の勤怠を取得
        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->with('breaks')
            ->orderBy('date')
            ->get();

        // Bladeに渡す表示用データ
        $formattedAttendanceRecords = $attendances->map(function ($attendance) {
            $totalBreakSeconds = 0;

            foreach ($attendance->breaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $totalBreakSeconds += Carbon::parse($break->break_in)
                        ->diffInSeconds(Carbon::parse($break->break_out));
                }
            }

            $totalWorkSeconds = 0;

            if ($attendance->clock_in && $attendance->clock_out) {
                $totalWorkSeconds = max(
                    0,
                    Carbon::parse($attendance->clock_in)
                        ->diffInSeconds(Carbon::parse($attendance->clock_out))
                        - $totalBreakSeconds
                );
            }

            return [
                'id' => $attendance->id,
                'date' => Carbon::parse($attendance->date)->format('m/d'),
                'clock_in' => $attendance->clock_in
                    ? Carbon::parse($attendance->clock_in)->format('H:i')
                    : '',
                'clock_out' => $attendance->clock_out
                    ? Carbon::parse($attendance->clock_out)->format('H:i')
                    : '',
                'total_break_time' => $totalBreakSeconds > 0
                    ? gmdate('H:i:s', $totalBreakSeconds)
                    : '',
                'total_time' => $totalWorkSeconds > 0
                    ? gmdate('H:i:s', $totalWorkSeconds)
                    : '',
            ];
        });

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}
