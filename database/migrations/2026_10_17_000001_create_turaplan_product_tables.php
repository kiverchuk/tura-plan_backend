<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->string('locale', 8)->default('en');
            $table->string('region', 16)->default('RO');
            $table->timestamp('email_verified_at')->nullable()->change();
            $table->timestamp('erasure_requested_at')->nullable();
            $table->string('admin_role', 32)->nullable();
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('platform', 20);
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        foreach (['employers', 'calendars', 'shift_types', 'shifts', 'personal_events', 'rotation_templates', 'pay_rules'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->json('data');
                $table->unsignedBigInteger('revision')->default(1);
                $table->timestampsTz();
                $table->softDeletesTz();
                $table->index(['user_id', 'updated_at']);
                if ($name === 'shifts') {
                    $table->index(['user_id', 'created_at']);
                }
            });
        }

        Schema::create('pay_rule_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('pay_rule_id')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('valid_from_day');
            $table->unsignedInteger('valid_to_day')->nullable();
            $table->unsignedBigInteger('rate_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('revision');
            $table->timestampsTz();
        });
        Schema::create('shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->json('scope');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'expires_at', 'revoked_at']);
        });
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store', 16);
            $table->string('product_id', 160);
            $table->string('original_transaction_id', 191)->nullable()->index();
            $table->string('status', 20);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->json('verified_payload')->nullable();
            $table->timestampsTz();
            $table->unique(['store', 'original_transaction_id']);
        });
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('state', 20);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'key']);
        });
        Schema::create('sync_mutations', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->uuid('entity_id');
            $table->string('operation', 16);
            $table->json('payload');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'id']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['user_id', 'entity_type', 'entity_id']);
        });
        Schema::create('sync_conflicts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 40);
            $table->uuid('entity_id');
            $table->unsignedBigInteger('base_revision')->nullable();
            $table->unsignedBigInteger('remote_revision');
            $table->json('local_payload');
            $table->json('remote_payload');
            $table->string('status', 20)->default('pending');
            $table->string('resolution', 20)->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'id']);
            $table->index(['user_id', 'status', 'created_at']);
        });
        Schema::create('holiday_packs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('country', 2);
            $table->string('region', 32)->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('locale', 8);
            $table->string('version', 32);
            $table->text('source_url');
            $table->text('license');
            $table->date('verified_at');
            $table->boolean('published')->default(false);
            $table->timestampsTz();
            $table->unique(['country', 'region', 'year', 'locale', 'version']);
        });
        Schema::create('holiday_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('holiday_pack_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 191);
            $table->string('kind', 32);
            $table->timestampsTz();
            $table->index(['holiday_pack_id', 'date']);
        });
        Schema::create('privacy_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('status', 24)->default('pending');
            $table->json('details')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'status']);
        });
        Schema::create('consents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 60);
            $table->boolean('granted');
            $table->string('policy_version', 32);
            $table->timestampTz('recorded_at');
            $table->timestampsTz();
            $table->index(['user_id', 'purpose', 'recorded_at']);
        });
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->string('status', 20)->default('open');
            $table->string('subject', 160);
            $table->text('message');
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at');
            $table->index(['admin_user_id', 'created_at']);
        });
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->unique(['user_id', 'key']);
        });
        Schema::create('export_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('format', 12);
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'status']);
        });
        Schema::create('feature_flags', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable();
            $table->timestampsTz();
        });
        Schema::create('store_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('store', 16);
            $table->string('event_id', 191);
            $table->string('status', 24);
            $table->string('error_code', 80)->nullable();
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['store', 'event_id']);
        });
    }

    public function down(): void
    {
        foreach (['store_webhook_events', 'feature_flags', 'export_requests', 'idempotency_keys', 'admin_audit_logs', 'support_tickets', 'consents', 'privacy_requests', 'holiday_entries', 'holiday_packs', 'sync_conflicts', 'sync_mutations', 'entitlements', 'subscriptions', 'shares', 'pay_rule_versions', 'pay_rules', 'rotation_templates', 'personal_events', 'shifts', 'shift_types', 'calendars', 'employers', 'devices'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'locale', 'region', 'erasure_requested_at', 'admin_role', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
