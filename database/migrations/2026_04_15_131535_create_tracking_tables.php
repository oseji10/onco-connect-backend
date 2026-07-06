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
        Schema::create('exit_logs', function (Blueprint $table) {
            $table->id('exitLogId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('attendeeId');
            $table->string('reason');
            $table->text('additionalNotes')->nullable();
            $table->timestamp('exitTime');
            $table->timestamp('returnTime')->nullable();
            $table->integer('durationMinutes')->nullable(); // Auto-calculated on return
            $table->enum('status', ['out', 'returned'])->default('out');
            $table->unsignedBigInteger('recordedBy'); // User who recorded exit
            $table->unsignedBigInteger('returnRecordedBy')->nullable(); // User who recorded return
            $table->timestamps();

            // Foreign keys
            $table->foreign('eventId')->references('eventId')->on('events')->onDelete('cascade');
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->onDelete('cascade');
            $table->foreign('recordedBy')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('returnRecordedBy')->references('id')->on('users')->onDelete('cascade');

            // Indexes for faster queries
            $table->index('eventId');
            $table->index('attendeeId');
            $table->index('status');
            $table->index('exitTime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_logs');
    }
};