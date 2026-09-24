<?php
// database/migrations/2026_01_01_000001_add_author_access_columns.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('abstract_authors', function (Blueprint $table) {
            if (! Schema::hasColumn('abstract_authors', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('abstract_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('abstract_authors', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('abstract_authors', 'activated_at')) {
                $table->timestamp('activated_at')->nullable()->after('invited_at');
            }
            if (! Schema::hasColumn('abstract_authors', 'is_corresponding')) {
                // already exists in your schema but guard anyway
            }
        });
    }

    public function down(): void
    {
        Schema::table('abstract_authors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['invited_at', 'activated_at']);
        });
    }
};