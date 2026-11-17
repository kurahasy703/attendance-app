<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceRecordsTableSeeder extends Seeder
{
    public function run(): void
    {
        // 一般ユーザー1を取得
        $user = User::where('email', 'user1@example.com')
            ->firstOrFail();

        // ① 過去5か月分：各月15日
        for ($month = 1; $month <= 5; $month++) {
            $date = Carbon::create(2026, 10, 1)
                ->subMonthsNoOverflow($month);

            $targetMonth = $date->month;
            $createdDays = 0;

            while (
                $date->month === $targetMonth
                && $createdDays < 15
            ) {
                if ($date->isWeekday()) {
                    $this->createAttendance(
                        $user,
                        $date->toDateString(),
                        '09:00:00',
                        '18:00:00'
                    );

                    $createdDays++;
                }

                $date->addDay();
            }
        }

        // ② 当月分：17日
        $workPatterns = [
            ['09:00:00', '18:00:00', 10], // 通常勤務
            ['09:00:00', '20:00:00', 3],  // 残業
            ['09:30:00', '18:00:00', 2],  // 遅刻
            ['09:00:00', '17:00:00', 1],  // 早退
            ['08:00:00', '21:00:00', 1],  // 長時間勤務
        ];

        $date = Carbon::create(2026, 10, 1);
        $endOfMonth = $date->copy()->endOfMonth();

        foreach ($workPatterns as $pattern) {
            [$clockIn, $clockOut, $days] = $pattern;

            for ($i = 0; $i < $days; $i++) {
                // 土日を飛ばす
                while ($date->isWeekend()) {
                    $date->addDay();
                }

                if ($date->gt($endOfMonth)) {
                    throw new \RuntimeException(
                        '当月に必要な勤務日を確保できません。'
                    );
                }

                $this->createAttendance(
                    $user,
                    $date->toDateString(),
                    $clockIn,
                    $clockOut
                );

                $date->addDay();
            }
        }
    }

    // 勤怠と休憩をセットで作成する共通処理
    private function createAttendance(
        User $user,
        string $date,
        string $clockIn,
        string $clockOut
    ): void {
        $attendance = $user->attendanceRecords()
            ->firstOrCreate(
                ['date' => $date],
                [
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                ]
            );

        $attendance->breaks()->firstOrCreate([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
