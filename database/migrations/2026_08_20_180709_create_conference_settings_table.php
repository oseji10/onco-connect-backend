<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conference_settings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('abstract_submission_deadline')->nullable();
            $table->timestamp('abstract_review_deadline')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conference_settings');
    }
};