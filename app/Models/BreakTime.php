<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakTime extends Model
{
    use HasFactory;

    // テーブル名を明示的に 'breaks' に指定
    protected $table = 'breaks';

    protected $fillable = [
        'attendance_record_id',
        'break_in',
        'break_out',
    ];

    // 勤怠記録への所属
    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class);
    }
}
