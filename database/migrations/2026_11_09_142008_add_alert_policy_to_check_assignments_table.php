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
        Schema::table('check_assignments', function (Blueprint $table) {
            $table->boolean('alert_enabled')->default(false);
            $table->smallInteger('failure_threshold')->default(3)->unsigned()->after('alert_enabled');
            $table->smallInteger('recovery_threshold')->default(1)->unsigned()->after('failure_threshold');
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_assignments', function (Blueprint $table) {
            $table->dropColumn(['alert_enabled', 'failure_threshold', 'recovery_threshold']);
        });
    }
};
