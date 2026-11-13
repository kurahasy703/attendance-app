<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'comment' => $this->comment,
        ];
    }
}
