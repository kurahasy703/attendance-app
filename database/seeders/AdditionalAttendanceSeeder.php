<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AdditionalAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // user2とuser3だけを対象にする
        $emails = [
            'user2@example.com',
            'user3@example.com',
        ];

        foreach ($emails as $email) {
            $user = User::where('email', $email)->firstOrFail();

            // 2026年5月〜10月の6か月間
            for ($month = 5; $month <= 10; $month++) {
                $date = Carbon::create(2026, $month, 1);
                $createdDays = 0;

                while (
                    $date->month === $month
                    && $createdDays < 15
                ) {
                    // 土日は勤務データを作らない
                    if ($date->isWeekday()) {
                        $dayNumber = $createdDays;

                        // 勤務パターンを変える
                        if ($dayNumber % 10 === 4) {
                            // 残業
                            $clockIn = '09:00:00';
                            $clockOut = '19:00:00';
                        } elseif ($dayNumber % 10 === 7) {
                            // 遅刻
                            $clockIn = '09:30:00';
                            $clockOut = '18:00:00';
                        } elseif ($dayNumber % 10 === 9) {
                            // 早退
                            $clockIn = '09:00:00';
                            $clockOut = '17:00:00';
                        } else {
                            // 通常勤務
                            $clockIn = '09:00:00';
                            $clockOut = '18:00:00';
                        }

                        // 同じ日付の既存データは変更しない
                        $attendance = $user->attendanceRecords()
                            ->firstOrCreate(
                                ['date' => $date->toDateString()],
                                [
                                    'clock_in' => $clockIn,
                                    'clock_out' => $clockOut,
                                ]
                            );

                        // 休憩が1件もない場合だけ追加する
                        if (!$attendance->breaks()->exists()) {
                            $attendance->breaks()->create([
                                'break_in' => '12:00:00',
                                'break_out' => '13:00:00',
                            ]);
                        }

                        $createdDays++;
                    }

                    $date->addDay();
                }
            }
        }
    }
}
