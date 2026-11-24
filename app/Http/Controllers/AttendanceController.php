<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use App\Models\StampCorrectionRequest;
use App\Models\StampCorrectionRequestBreak;
use Illuminate\Support\Facades\DB;


class AttendanceController extends Controller
{
    // PG03: 打刻画面表示 (GET /attendance)
    public function index()
    {
        $user = Auth::user();

        Carbon::setLocale('ja');
        $now = Carbon::now();
        $formattedDate = $now->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = $now->format('H:i');

        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->where('date', Carbon::today()->toDateString())
            ->first();

        $status = '勤務外';
        if ($attendance) {
            if ($attendance->clock_out) {
                $status = '退勤済';
            } elseif ($attendance->breaks()->whereNull('break_out')->exists()) {
                $status = '休憩中';
            } else {
                $status = '出勤中';
            }
        }

        $user->attendance_status = $status;

        return view('user.attendance-register', compact(
            'user',
            'attendance',
            'formattedDate',
            'formattedTime'
        ));
    }

    // 打刻登録処理 (POST /attendance)
    public function stamp(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today()->toDateString();
        $now = Carbon::now()->toTimeString();

        $action = $request->input('action');

        switch ($action) {
            case 'clock_in':
                AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => $today,
                    'clock_in' => $now,
                ]);
                break;

            case 'clock_out':
                $attendance = AttendanceRecord::where('user_id', $user->id)->where('date', $today)->first();
                if ($attendance) {
                    $attendance->update(['clock_out' => $now]);
                }
                break;

            case 'break_in':
                $attendance = AttendanceRecord::where('user_id', $user->id)->where('date', $today)->first();
                if ($attendance) {
                    $attendance->breaks()->create(['break_in' => $now]);
                }
                break;

            case 'break_out':
                $attendance = AttendanceRecord::where('user_id', $user->id)->where('date', $today)->first();
                if ($attendance) {
                    $break = $attendance->breaks()->whereNull('break_out')->first();
                    if ($break) {
                        $break->update(['break_out' => $now]);
                    }
                }
                break;
        }

        return redirect('/attendance');
    }

    // PG04: 勤怠一覧画面表示
    public function list(Request $request)
    {
        $user = Auth::user();

        // 表示する年月を取得（指定がなければ当月）
        $monthParam = $request->input('date', Carbon::now()->format('Y-m'));
        $targetDate = Carbon::parse($monthParam . '-01');

        $date = $targetDate;
        $currentMonth = $targetDate->format('Y-m');
        $previousMonth = $targetDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $targetDate->copy()->addMonth()->format('Y-m');

        // 指定月の勤怠データと休憩データを取得
        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->where('date', 'like', "{$currentMonth}%")
            ->with('breaks')
            ->orderBy('date', 'asc')
            ->get();

        // 画面表示用のデータを作成
        $formattedAttendanceRecords = $attendances->map(function ($attendance) {
            $totalBreakSeconds = 0;

            foreach ($attendance->breaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $breakIn = Carbon::parse($break->break_in);
                    $breakOut = Carbon::parse($break->break_out);

                    $totalBreakSeconds += $breakIn->diffInSeconds($breakOut);
                }
            }

            $totalWorkSeconds = 0;

            if ($attendance->clock_in && $attendance->clock_out) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalWorkSeconds = max(
                    0,
                    $clockIn->diffInSeconds($clockOut) - $totalBreakSeconds
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

        return view('user.user-attendance-list', compact(
            'attendances',
            'formattedAttendanceRecords',
            'currentMonth',
            'previousMonth',
            'nextMonth',
            'user',
            'date'
        ));
    }

    // PG05: 勤怠詳細画面表示 (GET /attendance/detail/{id})
    public function show($id)
    {
        $user = Auth::user();

        // ログイン中のユーザー本人の勤怠記録を取得
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->with('breaks')
            ->findOrFail($id);

        // この勤怠に紐づく承認待ちの申請を取得
        $application = StampCorrectionRequest::where(
            'attendance_record_id',
            $attendance->id
        )
            ->where('status', 'pending')
            ->with('stampCorrectionRequestBreaks')
            ->first();

        // 承認待ちの申請があれば申請内容、なければ実際の勤怠内容を使う
        $source = $application ?? $attendance;

        // 画面表示用の休憩データを作成
        if ($application) {
            $breaks = $application->stampCorrectionRequestBreaks;
        } else {
            $breaks = $attendance->breaks;
        }

        $formattedBreaks = $breaks->map(function ($break) {
            return [
                'break_in' => $break->break_in
                    ? Carbon::parse($break->break_in)->format('H:i')
                    : '',
                'break_out' => $break->break_out
                    ? Carbon::parse($break->break_out)->format('H:i')
                    : '',
            ];
        })->values()->all();

        // Bladeファイルが必要とする形式にまとめる
        $data = [
            'id' => $attendance->id,
            'year' => Carbon::parse($source->date)->format('Y年'),
            'date' => Carbon::parse($source->date)->format('m月d日'),
            'clock_in' => $source->clock_in
                ? Carbon::parse($source->clock_in)->format('H:i')
                : '',
            'clock_out' => $source->clock_out
                ? Carbon::parse($source->clock_out)->format('H:i')
                : '',
            'breaks' => $formattedBreaks,
            'comment' => $source->comment ?? '',
            'application' => $application,
        ];


        return view('user.user-detail', compact('data', 'user'));
    }

    // PG05: 勤怠修正申請の保存 (POST /attendance/{id})
    public function storeCorrectionRequest(Request $request, $id)
    {
        $user = Auth::user();

        // ログイン中のユーザー本人の勤怠だけを取得する
        $attendance = AttendanceRecord::where('user_id', $user->id)
            ->findOrFail($id);

        // すでに承認待ちの申請がある場合は、二重申請を防ぐ
        $pendingApplication = StampCorrectionRequest::where(
            'attendance_record_id',
            $attendance->id
        )
            ->where('status', 'pending')
            ->exists();

        if ($pendingApplication) {
            return back()->withErrors([
                'application' => 'この勤怠はすでに承認待ちです。',
            ]);
        }

        // フォームから送られた値を確認する
        $validated = $request->validate([
            'new_clock_in' => ['required', 'date_format:H:i'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in' => ['nullable', 'array'],
            'new_break_out' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'date_format:H:i'],
            'new_break_out.*' => ['nullable', 'date_format:H:i'],
            'comment' => ['required', 'string', 'max:255'],
        ], [
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
        ]);

        $breakIns = $validated['new_break_in'] ?? [];
        $breakOuts = $validated['new_break_out'] ?? [];
        // 休憩開始が出勤時刻より前になっていないか確認する
        foreach ($breakIns as $index => $breakIn) {
            if ($breakIn !== null && $breakIn < $validated['new_clock_in']) {
                return back()->withErrors([
                    'new_break_in.' . $index => '休憩時間が不適切な値です',
                ])->withInput();
            }
        }

        // 休憩終了が退勤時刻より後になっていないか確認する
        foreach ($breakOuts as $index => $breakOut) {
            if ($breakOut !== null && $breakOut > $validated['new_clock_out']) {
                return back()->withErrors([
                    'new_break_out.' . $index => '休憩時間もしくは退勤時間が不適切な値です',
                ])->withInput();
            }
        }

        // 休憩開始・終了の片方だけが入力されていないか確認する
        foreach (
            array_unique(array_merge(
                array_keys($breakIns),
                array_keys($breakOuts)
            )) as $index
        ) {
            $breakIn = $breakIns[$index] ?? '';
            $breakOut = $breakOuts[$index] ?? '';

            if (blank($breakIn) !== blank($breakOut)) {
                return back()->withErrors([
                    'new_break_in.' . $index => '休憩の開始時刻と終了時刻は両方入力してください。',
                ])->withInput();
            }
        }

        // 休憩開始が休憩終了より前になっているか確認する
        foreach ($breakIns as $index => $breakIn) {
            $breakOut = $breakOuts[$index] ?? null;

            if ($breakIn !== null && $breakOut !== null && $breakIn >= $breakOut) {
                return back()->withErrors([
                    'new_break_in.' . $index => '休憩時間が不適切な値です',
                ])->withInput();
            }
        }

        // 申請と休憩データをまとめて保存する
        DB::transaction(function () use (
            $attendance,
            $validated,
            $breakIns,
            $breakOuts
        ) {
            $application = StampCorrectionRequest::create([
                'attendance_record_id' => $attendance->id,
                'status' => 'pending',
                'date' => $attendance->date,
                'clock_in' => $validated['new_clock_in'],
                'clock_out' => $validated['new_clock_out'],
                'comment' => $validated['comment'],
            ]);

            foreach ($breakIns as $index => $breakIn) {
                $breakOut = $breakOuts[$index] ?? '';

                // 開始・終了が両方空欄の休憩行は保存しない
                if (blank($breakIn) && blank($breakOut)) {
                    continue;
                }

                StampCorrectionRequestBreak::create([
                    'stamp_correction_request_id' => $application->id,
                    'break_in' => $breakIn,
                    'break_out' => $breakOut,
                ]);
            }
        });

        // 保存後は勤怠詳細画面へ戻る
        return redirect()->route('attendance.detail', ['id' => $attendance->id]);
    }
}
