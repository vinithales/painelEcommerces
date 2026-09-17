<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_checks', fn (Blueprint $t) => $t->uuid('claim_token')->nullable());
    }

    public function down(): void
    {
        Schema::table('store_checks', fn (Blueprint $t) => $t->dropColumn('claim_token'));
    }
};
