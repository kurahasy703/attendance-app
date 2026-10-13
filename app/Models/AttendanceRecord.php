<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    // 1つの勤怠記録は複数の修正申請を持つ（1対多）
    public function stampCorrectionRequests()
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }
}
