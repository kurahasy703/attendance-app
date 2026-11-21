<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            'user_name' => $this->when(
                $request->is('api/v1/attendance-records'),
                fn() => $this->user?->name
            ),

            'user' => $this->when(
                !$request->is('api/v1/attendance-records'),
                fn() => new UserResource($this->whenLoaded('user'))
            ),

            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => sprintf(
                '%02d:%02d',
                intdiv($this->total_time, 60),
                $this->total_time % 60
            ),

            'total_break_time' => sprintf(
                '%02d:%02d',
                intdiv($this->total_break_time, 60),
                $this->total_break_time % 60
            ),
            'comment' => $this->comment,

            'breaks' => AttendanceBreakResource::collection(
                $this->whenLoaded('breaks')
            ),

            'applications' => ApplicationResource::collection(
                $this->whenLoaded('stampCorrectionRequests')
            ),
        ];
    }
}
