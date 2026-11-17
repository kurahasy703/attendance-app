<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    // ユーザーへの所属
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 1つの勤怠記録は複数の休憩記録を持つ
    public function breaks()
    {
        return $this->hasMany(BreakTime::class);
    }
    // 時間計算用の休憩情報
    public function breaksForCalculation()
    {
        return $this->hasMany(BreakTime::class);
    }

    // 1つの勤怠記録は複数の修正申請を持つ（1対多）
    public function stampCorrectionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }
    // 合計休憩時間を計算する（分）
    public function getTotalBreakTimeAttribute(): int
    {
        $breaks = $this->relationLoaded('breaks')
            ? $this->breaks
            : $this->breaksForCalculation;

        return (int) $breaks->sum(function ($break) {
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            return Carbon::parse($break->break_in)
                ->diffInMinutes(Carbon::parse($break->break_out));
        });
    }

    // 合計勤務時間を計算する（分）
    public function getTotalTimeAttribute(): int
    {
        if (!$this->clock_in || !$this->clock_out) {
            return 0;
        }

        $totalMinutes = Carbon::parse($this->clock_in)
            ->diffInMinutes(Carbon::parse($this->clock_out));

        return (int) max(0, $totalMinutes - $this->total_break_time);
    }
}
