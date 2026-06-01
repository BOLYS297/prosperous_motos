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
        Schema::table('ventes', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
            $table->boolean('is_offline')->default(false)->after('synced_at');
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
            $table->boolean('is_offline')->default(false)->after('synced_at');
        });

        Schema::table('pertes', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
            $table->boolean('is_offline')->default(false)->after('synced_at');
        });

        Schema::table('demande_transferts', function (Blueprint $table) {
            $table->uuid('client_uuid')->nullable()->unique()->after('id');
            $table->timestamp('synced_at')->nullable()->after('updated_at');
            $table->boolean('is_offline')->default(false)->after('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['client_uuid', 'synced_at', 'is_offline']);
        });

        Schema::table('depenses', function (Blueprint $table) {
            $table->dropColumn(['client_uuid', 'synced_at', 'is_offline']);
        });

        Schema::table('pertes', function (Blueprint $table) {
            $table->dropColumn(['client_uuid', 'synced_at', 'is_offline']);
        });

        Schema::table('demande_transferts', function (Blueprint $table) {
            $table->dropColumn(['client_uuid', 'synced_at', 'is_offline']);
        });
    }
};
