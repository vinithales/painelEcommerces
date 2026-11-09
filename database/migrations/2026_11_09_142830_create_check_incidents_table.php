<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('check_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_assignment_id')->constrained('check_assignments');
            $table->datetime('opened_at');
            $table->datetime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_incidents');
    }
};
