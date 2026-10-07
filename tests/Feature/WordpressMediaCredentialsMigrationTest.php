<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class WordpressMediaCredentialsMigrationTest extends TestCase
{
    public function test_it_adds_only_missing_credentials_and_preserves_existing_values(): void
    {
        foreach ([[], ['wp_username'], ['wp_application_password'], ['wp_username', 'wp_application_password']] as $existingColumns) {
            Schema::create('woocommerce_integrations', function (Blueprint $table) use ($existingColumns) {
                $table->id();
                foreach ($existingColumns as $column) {
                    $table->text($column)->nullable();
                }
            });

            try {
                $values = array_fill_keys($existingColumns, 'existing-value');
                DB::table('woocommerce_integrations')->insert(['id' => 1] + $values);

                $migration = require database_path('migrations/2026_10_06_000002_add_wordpress_media_credentials.php');
                $migration->up();
                $migration->up();

                $this->assertTrue(Schema::hasColumns('woocommerce_integrations', ['wp_username', 'wp_application_password']));
                $row = DB::table('woocommerce_integrations')->find(1);
                foreach (['wp_username', 'wp_application_password'] as $column) {
                    $this->assertSame($values[$column] ?? null, $row->{$column});
                }
            } finally {
                Schema::drop('woocommerce_integrations');
            }
        }
    }
}
