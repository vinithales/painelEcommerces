<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    
    public function up(): void
    {
        Schema::table('users', fn(Blueprint $t) => $t->string('role')->default('n1'));
        Schema::create('server_connections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('driver')->default('agent');
            $t->string('endpoint');
            $t->text('token');
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $t->string('resource');
            $t->string('action');
            $t->string('result');
            $t->json('parameters')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
        Schema::create('store_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('level');
            $t->string('check_type');
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('interval_seconds')->default(300);
            $t->string('severity')->default('CRITICAL');
            $t->json('configuration');
            $t->boolean('notify_on_failure')->default(true);
            $t->boolean('notify_on_recovery')->default(true);
            $t->unsignedInteger('consecutive_failures_before_alert')->default(2);
            $t->unsignedInteger('cooldown_seconds')->default(1800);
            $t->unsignedInteger('failure_count')->default(0);
            $t->string('last_status')->default('UNKNOWN');
            $t->string('alert_status')->nullable();
            $t->timestamp('last_alert_at')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('next_run_at')->nullable()->index();
            $t->timestamp('claimed_until')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('legacy_key')->nullable();
            $t->unique(['store_id', 'legacy_key']);
            $t->timestamps();
        });
        Schema::create('store_check_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_check_id')->constrained()->cascadeOnDelete();
            $t->string('status');
            $t->text('summary');
            $t->json('details')->nullable();
            $t->json('metrics')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at');
            $t->unsignedInteger('duration_ms');
            $t->boolean('manual')->default(false);
            $t->text('error_message')->nullable();
            $t->timestamps();
            $t->index(['store_check_id', 'created_at']);
        });
        Schema::create('store_check_alerts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('store_check_id')->constrained()->cascadeOnDelete();
            $t->string('from_status');
            $t->string('to_status');
            $t->string('channel')->default('email');
            $t->timestamp('sent_at')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        foreach (['store_check_alerts', 'store_check_runs', 'store_checks', 'audit_events', 'server_connections'] as $table)
            Schema::dropIfExists($table);
        Schema::table('users', fn(Blueprint $t) => $t->dropColumn('role'));
    }
};
