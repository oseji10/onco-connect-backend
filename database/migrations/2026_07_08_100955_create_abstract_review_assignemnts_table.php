<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_id')->constrained('abstracts')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('reviewers')->cascadeOnDelete();
            $table->enum('status', ['invited', 'in_progress', 'submitted'])->default('invited');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['abstract_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_assignments');
    }
};