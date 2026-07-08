<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_assignment_id')
                ->unique()
                ->constrained('review_assignments')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('significance'); // 1-5
            $table->unsignedTinyInteger('relevance'); // 1-5
            $table->unsignedTinyInteger('originality'); // 1-5
            $table->decimal('average', 3, 2);
            $table->text('comment')->nullable();
            $table->string('recommended_rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};