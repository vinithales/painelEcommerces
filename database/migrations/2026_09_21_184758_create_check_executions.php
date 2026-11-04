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
        Schema::create('check_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_assignment_id')->constrained('check_assignments');
            $table->string('status')->default('pending'); // pending, running, completed, failed
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('outcome')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_executions');
    }
};
