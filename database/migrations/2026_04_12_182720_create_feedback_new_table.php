<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_submissions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('attendeeId')->nullable();
            $table->foreign('attendeeId')
                  ->references('attendeeId')
                  ->on('attendees')
                  ->nullOnDelete();

            // General programme evaluation
            $table->tinyInteger('overall_rating');
            $table->tinyInteger('organization');
            $table->tinyInteger('communication');
            $table->enum('respected', ['yes', 'somewhat', 'no']);
            $table->enum('contributed_to_learning', ['yes', 'somewhat', 'no']);
            $table->enum('would_participate_again', ['yes', 'maybe', 'no']);

            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('feedback_staff_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('feedback_submission_id')
                  ->constrained('feedback_submissions')
                  ->cascadeOnDelete();

            // staff_id → users.id (staff are resolved from users via roles)
            $table->unsignedBigInteger('staff_id');
            $table->foreign('staff_id')
                  ->references('id')
                  ->on('users')
                  ->cascadeOnDelete();

            // Single performance rating — approachability and effectiveness removed
            $table->tinyInteger('performance');
            $table->text('strength')->nullable();
            $table->text('improvement')->nullable();

            $table->unique(['feedback_submission_id', 'staff_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_staff_ratings');
        Schema::dropIfExists('feedback_submissions');
    }
};