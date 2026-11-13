<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceExportController extends Controller
{
    public function export(Request $request)
    {
        // スタッフIDと年月を確認
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year_month' => ['required', 'date_format:Y-m'],
        ]);

        // 管理者以外のスタッフを取得
        $user = User::where('admin_status', false)
            ->findOrFail($validated['user_id']);

        $month = Carbon::createFromFormat(
            '!Y-m',
            $validated['year_month']
        );

        // 対象月の勤怠と休憩を取得
        $attendances = AttendanceRecord::where('user_id', $user->id)
            ->whereYear('date', $month->year)
            ->whereMonth('date', $month->month)
            ->with('breaks')
            ->orderBy('date')
            ->get();

        $filename = 'attendance_' . $user->id . '_'
            . $month->format('Y-m') . '.csv';

        // CSVをダウンロードする
        return response()->streamDownload(function () use ($attendances) {
            $stream = fopen('php://output', 'w');

            // Excelで日本語が文字化けしにくいようにする
            fwrite($stream, "\xEF\xBB\xBF");

            fputcsv($stream, [
                '日付',
                '出勤',
                '退勤',
                '休憩',
                '合計'
            ]);

            foreach ($attendances as $attendance) {
                $breakSeconds = 0;

                foreach ($attendance->breaks as $break) {
                    if ($break->break_in && $break->break_out) {
                        $breakSeconds += Carbon::parse($break->break_in)
                            ->diffInSeconds(Carbon::parse($break->break_out));
                    }
                }

                $workSeconds = 0;

                if ($attendance->clock_in && $attendance->clock_out) {
                    $workSeconds = max(
                        0,
                        Carbon::parse($attendance->clock_in)
                            ->diffInSeconds(Carbon::parse($attendance->clock_out))
                            - $breakSeconds
                    );
                }

                // 秒数を「時間:分」に変換
                $formatTime = function ($seconds) {
                    return sprintf(
                        '%02d:%02d',
                        intdiv($seconds, 3600),
                        intdiv($seconds % 3600, 60)
                    );
                };

                fputcsv($stream, [
                    $attendance->date,
                    $attendance->clock_in ?? '',
                    $attendance->clock_out ?? '',
                    $formatTime($breakSeconds),
                    $attendance->clock_out
                        ? $formatTime($workSeconds)
                        : '',
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
