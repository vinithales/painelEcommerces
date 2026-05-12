<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('operation_type'); // list, update
            $table->string('resource_type'); // product, order
            $table->string('resource_id')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('status'); // ok, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'resource_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_operation_logs');
    }
};
