<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_edit_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('resource_type'); // product, order
            $table->json('attribute_changes');
            $table->foreignId('store_id')->nullable()->constrained('stores')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_edit_templates');
    }
};
