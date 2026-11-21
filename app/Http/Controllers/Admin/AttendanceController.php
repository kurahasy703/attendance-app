<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $attendanceRecords = AttendanceRecord::whereDate('date', $date->toDateString())
            ->with('breaks')
            ->get()
            ->map(function ($attendance) {
                $breakMinutes = $attendance->total_break_time;
                $workMinutes = $attendance->total_time;

                return new \ArrayObject(
                    [
                        'id' => $attendance->id,
                        'user_id' => $attendance->user_id,
                        'clock_in' => $attendance->clock_in,
                        'clock_out' => $attendance->clock_out,
                        'total_break_time' => $breakMinutes > 0
                            ? sprintf('%02d:%02d:00', intdiv($breakMinutes, 60), $breakMinutes % 60)
                            : null,
                        'total_time' => $workMinutes > 0
                            ? sprintf('%02d:%02d:00', intdiv($workMinutes, 60), $workMinutes % 60)
                            : null,
                    ],
                    \ArrayObject::ARRAY_AS_PROPS
                );
            });
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
    // 管理者がスタッフの勤怠を直接修正する
    public function update(Request $request, $id)
    {
        // 入力内容を確認する
        $validated = $request->validate([
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
        ]);

        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];

        // 休憩時間に矛盾がないか確認する
        $breaks = [];

        foreach (
            array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            )) as $index
        ) {
            $breakIn = $breakIns[$index] ?? '';
            $breakOut = $breakOuts[$index] ?? '';

            // 両方空欄なら保存しない
            if (blank($breakIn) && blank($breakOut)) {
                continue;
            }

            // 片方だけ入力されている場合はエラー
            if (blank($breakIn) || blank($breakOut)) {
                return back()->withErrors([
                    'new_break_in.' . $index =>
                    '休憩開始・終了時刻は両方入力してください。',
                ])->withInput();
            }

            // 休憩は勤務時間内で、開始が終了より前であること
            if (
                $breakIn >= $breakOut ||
                $breakIn < $validated['new_clock_in'] ||
                $breakOut > $validated['new_clock_out']
            ) {
                return back()->withErrors([
                    'new_break_in.' . $index =>
                    '休憩時間を正しく入力してください。',
                ])->withInput();
            }

            $breaks[] = [
                'break_in' => $breakIn,
                'break_out' => $breakOut,
            ];
        }

        // 休憩同士の重複を確認する
        usort(
            $breaks,
            fn($a, $b) =>
            strcmp($a['break_in'], $b['break_in'])
        );

        for ($i = 1; $i < count($breaks); $i++) {
            if ($breaks[$i]['break_in'] < $breaks[$i - 1]['break_out']) {
                return back()->withErrors([
                    'new_break_in' => '休憩時間が重複しています。',
                ])->withInput();
            }
        }

        // 勤怠記録と休憩記録をまとめて更新する
        DB::transaction(function () use ($id, $validated, $breaks) {
            $attendance = AttendanceRecord::findOrFail($id);

            $attendance->update([
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'],
                'comment' => $validated['comment'],
            ]);

            // 既存の休憩記録を置き換える
            $attendance->breaks()->delete();

            foreach ($breaks as $break) {
                $attendance->breaks()->create($break);
            }
        });

        // 更新後は管理者の勤怠詳細画面に戻る
        return redirect()->route('admin.attendance.detail', ['id' => $id]);
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
