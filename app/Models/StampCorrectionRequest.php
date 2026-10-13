<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StampCorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_record_id',
        'status',
        'clock_in',
        'clock_out',
        'comment',
    ];

    // 勤怠記録への所属
    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    // 1つの申請は複数の申請用休憩記録を持つ
    public function stampCorrectionRequestBreaks()
    {
        return $this->hasMany(StampCorrectionRequestBreak::class);
    }
}
