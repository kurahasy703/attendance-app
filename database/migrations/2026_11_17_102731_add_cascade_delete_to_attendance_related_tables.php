<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 休憩記録
        Schema::table('breaks', function (Blueprint $table) {
            $table->dropForeign(['attendance_record_id']);

            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records')
                ->cascadeOnDelete();
        });

        // 修正申請
        Schema::table('stamp_correction_requests', function (Blueprint $table) {
            $table->dropForeign(['attendance_record_id']);

            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records')
                ->cascadeOnDelete();
        });

        // 修正申請の休憩記録
        Schema::table('stamp_correction_request_breaks', function (Blueprint $table) {
            $table->dropForeign('scr_breaks_request_fk');

            $table->foreign('stamp_correction_request_id', 'scr_breaks_request_fk')
                ->references('id')
                ->on('stamp_correction_requests')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // 休憩記録
        Schema::table('breaks', function (Blueprint $table) {
            $table->dropForeign(['attendance_record_id']);

            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records');
        });

        // 修正申請
        Schema::table('stamp_correction_requests', function (Blueprint $table) {
            $table->dropForeign(['attendance_record_id']);

            $table->foreign('attendance_record_id')
                ->references('id')
                ->on('attendance_records');
        });

        // 修正申請の休憩記録
        Schema::table('stamp_correction_request_breaks', function (Blueprint $table) {
            $table->dropForeign('scr_breaks_request_fk');

            $table->foreign('stamp_correction_request_id', 'scr_breaks_request_fk')
                ->references('id')
                ->on('stamp_correction_requests');
        });
    }
};
