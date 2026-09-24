<?php
// database/migrations/2026_01_01_000003_add_resubmission_flags_to_assignments.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('review_assignments', 'is_resubmission_review')) {
                $table->boolean('is_resubmission_review')->default(false)->after('status');
            }
            if (! Schema::hasColumn('review_assignments', 'source_assignment_id')) {
                $table->foreignId('source_assignment_id')->nullable()->after('is_resubmission_review')
                    ->constrained('review_assignments')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_assignment_id');
            $table->dropColumn('is_resubmission_review');
        });
    }
};