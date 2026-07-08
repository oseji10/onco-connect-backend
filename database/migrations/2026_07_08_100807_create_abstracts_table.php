<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abstracts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('title');
            $table->string('sub_theme');
            $table->enum('presentation_type', ['Oral', 'Poster', 'Either']);
            $table->string('keywords')->nullable();
            $table->text('body');
            $table->unsignedInteger('word_count')->default(0);
            $table->enum('status', [
                'submitted',
                'under_review',
                'scored',
                'accepted',
                'rejected',
            ])->default('submitted');
            $table->decimal('average_score', 3, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['sub_theme']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abstracts');
    }
};