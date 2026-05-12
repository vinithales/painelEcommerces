<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magento2_integrations', function (Blueprint $table) {
            $table->text('username')->nullable()->after('access_token_secret');
            $table->text('password')->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('magento2_integrations', function (Blueprint $table) {
            $table->dropColumn(['username', 'password']);
        });
    }
};
