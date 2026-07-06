<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id('attendanceId');

            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('attendeeId');
            $table->unsignedBigInteger('eventPassId');

            $table->date('attendanceDate');
            $table->timestamp('markedAt')->nullable();

            $table->unsignedBigInteger('markedBy')->nullable();
            $table->string('deviceName')->nullable();
            $table->string('scanSource')->nullable(); // qr, barcode, manual
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->cascadeOnDelete();
            $table->foreign('eventPassId')->references('passId')->on('event_passes')->cascadeOnDelete();
            $table->foreign('markedBy')->references('id')->on('users')->nullOnDelete();

            $table->unique(['eventId', 'eventPassId', 'attendanceDate'], 'event_pass_daily_attendance_unique');
            $table->index(['eventId', 'attendanceDate']);
            $table->index(['attendeeId', 'attendanceDate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};