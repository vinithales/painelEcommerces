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
        Schema::table('check_executions', function (Blueprint $table) {
            $table->json('definition_snapshot')->nullable()->after('check_assignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_executions', function (Blueprint $table) {
            $table->dropColumn('definition_snapshot');
        });
    }
};
