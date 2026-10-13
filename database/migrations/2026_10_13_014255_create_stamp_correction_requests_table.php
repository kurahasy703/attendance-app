<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attendance_record_id')
                ->constrained('attendance_records');

            $table->enum('status', ['pending', 'approved']);

            $table->date('date');
            $table->time('clock_in');
            $table->time('clock_out');

            $table->string('comment', 255);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
};
