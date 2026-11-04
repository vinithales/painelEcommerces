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
        Schema::create('check_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_definition_id')->constrained('check_definitions');
            $table->foreignId('store_id')->constrained('stores');
            $table->unsignedBigInteger('interval_minutes');
            $table->boolean('enabled')->default(true);
            $table->unique(['check_definition_id', 'store_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_assignments');
    }
};
