<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test/dev bootstrap mirror of {@code docs/schema.sql}. Production schema is
 * managed manually by ops (per project policy: schema.sql is source of truth);
 * this migration only exists so the sqlite in-memory test runner has a usable
 * database. Keep both files in lockstep when columns / indexes change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biz_game', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('raw_id');
            $table->unsignedTinyInteger('status')->default(1);
            $table->json('winning_selection_ids')->nullable();
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->unique('raw_id');
        });

        Schema::create('biz_market', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('game_id')->index('idx_biz_market_game');
            $table->string('name', 256)->default('');
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('biz_selection', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('market_id')->index('idx_biz_selection_market');
            $table->string('label', 256)->default('');
            $table->unsignedInteger('current_odds_millis')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('bet_order', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0)->index('idx_bet_order_user');
            $table->unsignedBigInteger('idem_key');
            $table->integer('total_price')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->integer('points_held')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->unique(['uid', 'idem_key'], 'uni_bet_order_uid_idem_key');
        });

        Schema::create('order_item', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('oid')->default(0)->index('idx_order_item_oid');
            $table->unsignedBigInteger('kid')->default(0)->index('idx_order_item_kid');
            $table->unsignedInteger('stake_points')->default(0);
            $table->text('odds_snapshot')->nullable();
            $table->unsignedInteger('decimal_odds_millis')->default(0);
            $table->unsignedBigInteger('potential_return_points')->default(0);
            $table->unsignedTinyInteger('result')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('settle_job', function (Blueprint $table): void {
            $table->id();
            $table->string('biz_key', 128)->unique('uni_settle_biz_key');
            $table->text('payload')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('cursor_offset')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('points_balance', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->unique('uni_bet_points_bal_user');
            $table->bigInteger('balance')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('points_flow', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0);
            $table->unsignedBigInteger('oid')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->unsignedTinyInteger('state')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index(['uid', 'oid'], 'idx_bet_points_flow_user_order');
            $table->unique(['oid', 'state'], 'uni_bet_points_flow_oid_state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_flow');
        Schema::dropIfExists('points_balance');
        Schema::dropIfExists('settle_job');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('bet_order');
        Schema::dropIfExists('biz_selection');
        Schema::dropIfExists('biz_market');
        Schema::dropIfExists('biz_game');
    }
};
