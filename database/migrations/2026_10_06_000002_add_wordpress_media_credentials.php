<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            $table->string('wp_username')->nullable();
            $table->text('wp_application_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_integrations', function (Blueprint $table) {
            $table->dropColumn(['wp_username', 'wp_application_password']);
        });
    }
};
