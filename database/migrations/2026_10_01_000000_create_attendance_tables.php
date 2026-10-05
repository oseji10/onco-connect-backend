<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_sessions', function (Blueprint $t) {
            $t->id('sessionId');
            $t->unsignedBigInteger('eventId')->index();
            $t->string('title');
            $t->dateTime('startsAt');
            $t->dateTime('endsAt');
            $t->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $t) {
            $t->id('attendanceId');
            $t->unsignedBigInteger('attendeeId')->index();
            $t->unsignedBigInteger('sessionId')->index();
            $t->string('method')->default('self'); // self | venue | manual
            $t->dateTime('checkedInAt');
            $t->unsignedBigInteger('recordedBy')->nullable();
            $t->timestamps();
            $t->unique(['attendeeId', 'sessionId']);
        });

        Schema::create('event_feedback', function (Blueprint $t) {
            $t->id('feedbackId');
            $t->unsignedBigInteger('attendeeId')->unique();
            $t->unsignedBigInteger('eventId')->index();
            $t->unsignedTinyInteger('rating');
            $t->text('takeaway');
            $t->text('comments')->nullable();
            $t->timestamps();
        });

        Schema::table('attendees', function (Blueprint $t) {
            $t->boolean('certificateEligible')->default(false);
            $t->dateTime('certificateEligibleAt')->nullable();
            $t->boolean('manualOverride')->default(false);
            $t->text('manualOverrideReason')->nullable();
            $t->unsignedBigInteger('manualOverrideBy')->nullable();
            $t->dateTime('manualOverrideAt')->nullable();
        });

        Schema::table('events', function (Blueprint $t) {
            // null = attendee must check in to ALL sessions
            $t->unsignedSmallInteger('requiredSessions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn('requiredSessions'));
        Schema::table('attendees', fn (Blueprint $t) => $t->dropColumn([
            'certificateEligible', 'certificateEligibleAt', 'manualOverride',
            'manualOverrideReason', 'manualOverrideBy', 'manualOverrideAt',
        ]));
        Schema::dropIfExists('event_feedback');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('event_sessions');
    }
};
