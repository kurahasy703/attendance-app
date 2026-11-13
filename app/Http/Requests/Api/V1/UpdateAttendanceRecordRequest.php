<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')
                    ->where('user_id', $this->route('attendanceRecord')->user_id)
                    ->ignore($this->route('attendanceRecord')->id),
            ],
            'clock_in' => [
                'sometimes',
                'required',
                'date_format:H:i:s',
            ],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // 入力形式にエラーがある場合は、ここでは比較しない
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // 更新対象の勤怠記録
            $record = $this->route('attendanceRecord');

            // 送信された時刻を優先し、未送信なら保存済みの時刻を使う
            $clockIn = $this->input('clock_in', $record->clock_in);
            $clockOut = $this->input('clock_out', $record->clock_out);

            // 退勤時刻がある場合だけ前後関係を確認する
            if ($clockOut !== null && $clockOut <= $clockIn) {
                $validator->errors()->add(
                    'clock_out',
                    '退勤時刻は出勤時刻より後にしてください。'
                );
            }
        });
    }
}
