<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->ipAddress('ssh_ip')->nullable();
            $table->string('ssh_user', 64)->nullable();
            $table->text('ssh_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['ssh_ip', 'ssh_user', 'ssh_password']));
    }
};
