<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('store_integration_status', 'platform')) {
            DB::statement('ALTER TABLE `store_integration_status` CHANGE COLUMN `platform` `check_key` VARCHAR(255) NOT NULL');
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
            DB::statement('ALTER TABLE `store_integration_status` CHANGE COLUMN `check_key` `platform` VARCHAR(255) NOT NULL');
        }
    }
};