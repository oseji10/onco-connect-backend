<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstracts', function (Blueprint $table) {
            // Position of this abstract in the overall (top-30) ranking. Null if not in it.
            $table->unsignedInteger('overall_rank')->nullable()->after('average_score');

            // Position of this abstract within its sub-theme's top-5. Null if not in it.
            $table->unsignedInteger('sub_theme_rank')->nullable()->after('overall_rank');

            // How this abstract was bucketed by the ranking run:
            // top30 | sub_theme_top5 | poster | pending | manual
            $table->string('classification_group')->nullable()->after('sub_theme_rank');

            // When the decision email for this abstract was last sent. Null = not yet notified.
            $table->timestamp('decision_notified_at')->nullable()->after('classification_group');

            $table->index('classification_group');
            $table->index('overall_rank');
            $table->index('decision_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('abstracts', function (Blueprint $table) {
            $table->dropIndex(['classification_group']);
            $table->dropIndex(['overall_rank']);
            $table->dropIndex(['decision_notified_at']);
            $table->dropColumn(['overall_rank', 'sub_theme_rank', 'classification_group', 'decision_notified_at']);
        });
    }
};