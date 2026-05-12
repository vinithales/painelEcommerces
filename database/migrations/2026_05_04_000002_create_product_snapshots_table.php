<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('resource_id');
            $table->string('platform');
            $table->json('data_before');
            $table->json('data_after')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_snapshots');
    }
};
