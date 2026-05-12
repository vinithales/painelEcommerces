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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform');
            $table->string('store_url');
            $table->string('panel_prefix')->nullable();
            $table->string('payment_gateway')->nullable();
            $table->string('shipping_gateway')->nullable();
            $table->timestamps();
        });

        Schema::create('woocommerce_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->timestamps();
        });

        Schema::create('magento2_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->text('consumer_key');
            $table->text('consumer_secret');
            $table->text('access_token');
            $table->text('access_token_secret');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('magento2_integrations');
        Schema::dropIfExists('woocommerce_integrations');
        Schema::dropIfExists('clients');
    }
};