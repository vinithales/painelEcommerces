<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_integration_status', 'platform')) {
            Schema::table('store_integration_status', fn (Blueprint $table) => $table->renameColumn('platform', 'check_key'));
        }

        Schema::table('store_integration_status', function (Blueprint $table) {
            $table->unique(['store_id', 'check_key'], 'store_integration_status_store_id_check_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('store_integration_status', function (Blueprint $table) {
            $table->dropUnique('store_integration_status_store_id_check_key_unique');
        });

        if (Schema::hasColumn('store_integration_status', 'check_key')) {
            Schema::table('store_integration_status', fn (Blueprint $table) => $table->renameColumn('check_key', 'platform'));
        }
    }
};
