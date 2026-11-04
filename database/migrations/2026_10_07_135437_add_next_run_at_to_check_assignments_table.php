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
            $table->dateTime('next_run_at')->nullable();
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_assignments', function (Blueprint $table) {
            $table->dropIndex(['next_run_at']);
            $table->dropColumn('next_run_at');
        });
    }
};
