<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    public function index(Request $request)
    {
        // ログイン中のユーザーを取得
        $user = Auth::user();


        // URLのmonthを確認する（例：2026-10）
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        // 指定がなければ現在月を使う
        $targetMonth = isset($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'])
            : Carbon::now()->startOfMonth();

        // 対象月を含む過去6か月
        $startDate = $targetMonth->copy()
            ->startOfMonth()
            ->subMonthsNoOverflow(5);

        $endDate = $targetMonth->copy()->endOfMonth();

        // 勤怠と休憩をまとめて取得
        $records = AttendanceRecord::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->get();

        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;

        $monthlyTrend = [];

        // 6か月分の月次データを初期化
        for ($i = 0; $i < 6; $i++) {
            $month = $startDate->copy()->addMonthsNoOverflow($i)
                ->format('Y-m');

            $monthlyTrend[$month] = [
                'month' => $month,
                'work_minutes' => 0,
                'overtime_minutes' => 0,
            ];
        }

        $anomalies = [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ];

        $workedDays = 0;

        foreach ($records as $record) {
            // 退勤していない勤怠は集計しない
            if (!$record->clock_out) {
                continue;
            }

            $date = Carbon::parse($record->date);

            $clockIn = Carbon::parse($record->date . ' ' . $record->clock_in);
            $clockOut = Carbon::parse($record->date . ' ' . $record->clock_out);

            // 出勤から退勤までの時間
            $workMinutes = $clockIn->diffInMinutes($clockOut);

            // 休憩時間を差し引く
            foreach ($record->breaks as $break) {
                if ($break->break_in && $break->break_out) {
                    $breakIn = Carbon::parse(
                        $record->date . ' ' . $break->break_in
                    );
                    $breakOut = Carbon::parse(
                        $record->date . ' ' . $break->break_out
                    );

                    $workMinutes -= $breakIn->diffInMinutes($breakOut);
                }
            }

            $workMinutes = max(0, $workMinutes);

            // 8時間を超えた分を残業として計算
            $overtimeMinutes = max(0, $workMinutes - 480);

            $totalWorkMinutes += $workMinutes;
            $totalOvertimeMinutes += $overtimeMinutes;
            $workedDays++;

            // 月次集計
            $month = $date->format('Y-m');

            $monthlyTrend[$month]['work_minutes'] += $workMinutes;
            $monthlyTrend[$month]['overtime_minutes'] += $overtimeMinutes;

            // 選択した月の異常検知
            if ($date->isSameMonth($targetMonth)) {
                if ($record->clock_in > '09:00:00') {
                    $anomalies['late_count']++;
                }

                if ($record->clock_out < '18:00:00') {
                    $anomalies['early_leave_count']++;
                }

                if ($workMinutes > 600) {
                    $anomalies['long_work_count']++;
                }
            }
        }

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $workedDays > 0
                ? (int) round($totalWorkMinutes / $workedDays)
                : 0,
        ];

        return view('reports.index', [
            'summary' => $summary,
            'monthlyTrend' => array_values($monthlyTrend),
            'anomalies' => $anomalies,
        ]);
    }
}
