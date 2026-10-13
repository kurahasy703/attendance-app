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

        Schema::create('stamp_correction_request_breaks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_correction_request_id')
                ->constrained(
                    table: 'stamp_correction_requests',
                    indexName: 'scr_breaks_request_fk'
                );

            $table->time('break_in');
            $table->time('break_out');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_request_breaks');
    }
};
