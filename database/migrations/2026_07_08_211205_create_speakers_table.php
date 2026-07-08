<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // Session
            $table->enum('session_type', ['Keynote', 'Plenary', 'Panel', 'Breakout']);
            $table->string('sub_theme');
            $table->string('session_title');
            $table->text('session_description');
            $table->enum('participation_type', ['Physical', 'Virtual']);

            // Personal
            $table->string('title');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_names')->nullable();
            $table->string('organization');
            $table->string('job_title');
            $table->text('bio');
            $table->boolean('physically_challenged')->default(false);
            $table->text('accessibility_needs')->nullable();

            // Contact
            $table->string('email');
            $table->string('country')->default('Nigeria');
            $table->string('state');
            $table->string('phone_country_code', 10)->default('+234');
            $table->string('phone_number');
            $table->string('linkedin_url')->nullable();
            $table->string('twitter_handle')->nullable();

            // Files (stored paths, resolved to full URLs in the API resource)
            $table->string('photo_path');
            $table->string('cv_path');

            $table->enum('status', ['submitted', 'confirmed', 'rejected'])->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['session_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('speakers');
    }
};