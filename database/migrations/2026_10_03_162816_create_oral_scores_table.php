<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oral_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('abstract_id')->constrained('abstracts')->cascadeOnDelete();
            $table->foreignId('panelist_id')->constrained('oral_panelists')->cascadeOnDelete();

            // The six criteria, each 1-5 (ICW 2026 scoring guideline).
            $table->unsignedTinyInteger('presentation_of_results');
            $table->unsignedTinyInteger('visual_aids');
            $table->unsignedTinyInteger('clarity_organization');
            $table->unsignedTinyInteger('presenter_performance');
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('response_to_questions');

            $table->unsignedTinyInteger('total');      // out of 30
            $table->decimal('percentage', 5, 2);       // total / 30 x 100
            $table->text('comment')->nullable();
            $table->timestamps();

            // One score per panelist per presentation (re-submitting edits it).
            $table->unique(['abstract_id', 'panelist_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oral_scores');
    }
};