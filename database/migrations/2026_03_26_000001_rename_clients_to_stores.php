<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('clients') && ! Schema::hasTable('stores')) {
            Schema::rename('clients', 'stores');
        }

        if (DB::getDriverName() === 'sqlite') {
            // SQLite updates existing foreign keys and unique indexes on rename.
            foreach (['woocommerce_integrations', 'magento2_integrations'] as $table) {
                if (Schema::hasColumn($table, 'client_id')) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn('client_id', 'store_id'));
                }
            }

            return;
        }

        $this->dropForeignKeyIfExists('woocommerce_integrations', 'client_id');
        $this->dropIndexIfExists('woocommerce_integrations', 'client_id');
        $this->dropForeignKeyIfExists('magento2_integrations', 'client_id');
        $this->dropIndexIfExists('magento2_integrations', 'client_id');

        if (Schema::hasColumn('woocommerce_integrations', 'client_id')) {
            DB::statement('ALTER TABLE `woocommerce_integrations` CHANGE COLUMN `client_id` `store_id` BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('magento2_integrations', 'client_id')) {
            DB::statement('ALTER TABLE `magento2_integrations` CHANGE COLUMN `client_id` `store_id` BIGINT UNSIGNED NOT NULL');
        }

        $this->dropForeignKeyIfExists('woocommerce_integrations', 'store_id');
        $this->dropIndexIfExists('woocommerce_integrations', 'store_id');
        $this->dropForeignKeyIfExists('magento2_integrations', 'store_id');
        $this->dropIndexIfExists('magento2_integrations', 'store_id');

        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            $table->unique('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('magento2_integrations', function (Blueprint $table) {
            $table->unique('store_id');
            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (['woocommerce_integrations', 'magento2_integrations'] as $table) {
                if (Schema::hasColumn($table, 'store_id')) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn('store_id', 'client_id'));
                }
            }
            if (Schema::hasTable('stores') && ! Schema::hasTable('clients')) {
                Schema::rename('stores', 'clients');
            }

            return;
        }
        $this->dropForeignKeyIfExists('woocommerce_integrations', 'store_id');
        $this->dropIndexIfExists('woocommerce_integrations', 'store_id');
        $this->dropForeignKeyIfExists('magento2_integrations', 'store_id');
        $this->dropIndexIfExists('magento2_integrations', 'store_id');

        if (Schema::hasColumn('woocommerce_integrations', 'store_id')) {
            DB::statement('ALTER TABLE `woocommerce_integrations` CHANGE COLUMN `store_id` `client_id` BIGINT UNSIGNED NOT NULL');
        }

        if (Schema::hasColumn('magento2_integrations', 'store_id')) {
            DB::statement('ALTER TABLE `magento2_integrations` CHANGE COLUMN `store_id` `client_id` BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            $table->unique('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        Schema::table('magento2_integrations', function (Blueprint $table) {
            $table->unique('client_id');
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        if (Schema::hasTable('stores') && ! Schema::hasTable('clients')) {
            Schema::rename('stores', 'clients');
        }
    }

    private function dropForeignKeyIfExists(string $tableName, string $columnName): void
    {
        $databaseName = DB::getDatabaseName();

        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($foreignKeys as $foreignKeyName) {
            DB::statement("ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$foreignKeyName}`");
        }
    }

    private function dropIndexIfExists(string $tableName, string $columnName): void
    {
        $databaseName = DB::getDatabaseName();

        $indexes = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME')
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->where('INDEX_NAME', '!=', 'PRIMARY')
            ->distinct()
            ->pluck('INDEX_NAME');

        foreach ($indexes as $indexName) {
            DB::statement("ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`");
        }
    }
};
