<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Foreign keys already defined in create_deductions_table migration via ->constrained()
        // This migration is intentionally a no-op to avoid duplicate key errors.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for existing table verification
    }
};
