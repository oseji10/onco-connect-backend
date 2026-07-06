<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->bigIncrements('certificateId');
            $table->unsignedBigInteger('eventId');
            $table->unsignedBigInteger('attendeeId');
            $table->string('type');
            $table->string('certificateNumber')->unique();
            $table->unsignedBigInteger('issuedBy')->nullable();
            $table->timestamp('issuedAt')->nullable();
            $table->timestamp('sentAt')->nullable();
            $table->timestamps();

            // One certificate per attendee per type per event.
            $table->unique(['eventId', 'attendeeId', 'type']);
            $table->index(['eventId', 'attendeeId']);

            // FKs — adjust/remove if your key column types differ.
            $table->foreign('attendeeId')->references('attendeeId')->on('attendees')->cascadeOnDelete();
            $table->foreign('eventId')->references('eventId')->on('events')->cascadeOnDelete();
            $table->foreign('issuedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};