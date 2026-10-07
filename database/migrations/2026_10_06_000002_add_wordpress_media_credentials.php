<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('woocommerce_integrations', 'wp_username')) {
            Schema::table('woocommerce_integrations', function (Blueprint $table) {
                $table->string('wp_username')->nullable();
            });
        }

        if (! Schema::hasColumn('woocommerce_integrations', 'wp_application_password')) {
            Schema::table('woocommerce_integrations', function (Blueprint $table) {
                $table->text('wp_application_password')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            $table->dropColumn(['wp_username', 'wp_application_password']);
        });
    }
};
