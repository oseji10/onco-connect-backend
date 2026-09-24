<?php
// database/migrations/2026_01_01_000002_add_versioning_to_abstracts.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstracts', function (Blueprint $table) {
            if (! Schema::hasColumn('abstracts', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')
                    ->constrained('abstracts')->nullOnDelete();
            }
            if (! Schema::hasColumn('abstracts', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('parent_id');
            }
            if (! Schema::hasColumn('abstracts', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('version');
            }
            if (! Schema::hasColumn('abstracts', 'resubmission_note')) {
                $table->text('resubmission_note')->nullable()->after('is_current');
            }
            if (! Schema::hasColumn('abstracts', 'resubmitted_at')) {
                $table->timestamp('resubmitted_at')->nullable()->after('resubmission_note');
            }

            $table->index(['parent_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::table('abstracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['version', 'is_current', 'resubmission_note', 'resubmitted_at']);
        });
    }
};