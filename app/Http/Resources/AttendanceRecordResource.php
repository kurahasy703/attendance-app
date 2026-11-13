<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 休憩時間の合計（分）
        $totalBreakMinutes = $this->breaks->sum(function ($break) {
            if (!$break->break_in || !$break->break_out) {
                return 0;
            }

            return Carbon::parse($break->break_in)
                ->diffInMinutes(Carbon::parse($break->break_out));
        });

        // 勤務時間の合計（分）
        $totalWorkMinutes = 0;

        if ($this->clock_in && $this->clock_out) {
            $totalWorkMinutes = Carbon::parse($this->clock_in)
                ->diffInMinutes(Carbon::parse($this->clock_out))
                - $totalBreakMinutes;
        }


        // AP02：勤怠詳細API
        if ($request->routeIs('api.attendance-records.show')) {
            return [
                'id' => $this->id,
                'user' => [
                    'id' => $this->user?->id,
                    'name' => $this->user?->name,
                ],
                'date' => $this->date,
                'clock_in' => $this->clock_in,
                'clock_out' => $this->clock_out,
                'breaks' => AttendanceBreakResource::collection(
                    $this->whenLoaded('breaks')
                ),
                'applications' => ApplicationResource::collection(
                    $this->whenLoaded('stampCorrectionRequests')
                ),
                'comment' => $this->comment,
            ];
        }

        // AP01：勤怠一覧API（これまでの処理を維持）
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => max(0, $totalWorkMinutes),
            'total_break_time' => $totalBreakMinutes,
            'comment' => $this->comment,
        ];
    }
}
