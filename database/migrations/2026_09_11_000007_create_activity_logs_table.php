<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-level activity trail (login / logout / store-settings changes).
 *
 * Naming follows the modern app-table convention (plain snake_case, matching
 * sms_logs / sms_campaigns) — NOT the legacy `db_` domain prefix, because an
 * activity log is infrastructure/telemetry rather than a ported domain entity.
 *
 * Deliberately a SINGLE table (no separate `store_access_logs`): the `action`
 * column distinguishes login/logout/settings_updated. Store-switch logging is
 * intentionally NOT represented — there is no store-switching feature in this
 * application, so no such event can currently be produced.
 *
 * NOTE: `store_id` is nullable because a login record is written immediately
 * after `auth()->attempt()` resolves; while every valid user in this app always
 * carries a `store_id`, a future Super-Admin-without-store flow must not break
 * the auth path. `user_id` is nullable to survive user deletion.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            // A log row is never updated — created_at only (no updated_at).
            $table->timestamp('created_at')->nullable();

            $table->index(['store_id', 'created_at']);
            $table->index('user_id');
            $table->index('action');

            // Deliberately NO hard foreign keys here, unlike a domain table.
            // A user may carry a `store_id` with no matching db_store row (the
            // app explicitly tolerates this — see EnsureUserHasStore, which
            // 403s on an unresolvable store rather than guaranteeing one). A
            // hard FK would make the AUDIT write fail and, because logging runs
            // inside the auth controller, would break login/logout entirely.
            // Plain indexed nullable bigints keep the trail write non-fatal.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
