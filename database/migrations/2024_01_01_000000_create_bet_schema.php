<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biz_event', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 512);
            $table->unsignedBigInteger('starts_at')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('winning_selection_ids')->nullable();
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('biz_market', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedSmallInteger('market_type')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('event_id', 'idx_biz_market_event');
        });

        Schema::create('biz_selection', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->string('label', 256)->default('');
            $table->unsignedInteger('current_odds_millis')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('market_id', 'idx_biz_selection_market');
        });

        Schema::create('order', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0);
            $table->integer('total_price')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedSmallInteger('checkout_phase')->default(0);
            $table->boolean('ext_inventory')->default(false);
            $table->string('ext_id', 128)->default('');
            $table->integer('points_deduct_minor')->default(0);
            $table->integer('cash_payable_minor')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('uid', 'idx_bet_order_user');
        });

        Schema::create('order_item', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('oid')->default(0);
            $table->unsignedBigInteger('kid')->default(0);
            $table->unsignedInteger('stake_points')->default(0);
            $table->text('odds_snapshot')->nullable();
            $table->unsignedInteger('decimal_odds_millis')->default(0);
            $table->unsignedBigInteger('potential_return_points')->default(0);
            $table->unsignedTinyInteger('result')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('oid', 'idx_order_item_oid');
            $table->index('kid', 'idx_order_item_kid');
        });

        Schema::create('points_balance', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid');
            $table->bigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->unique('uid', 'uni_bet_points_bal_user');
        });

        Schema::create('points_flow', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0);
            $table->unsignedBigInteger('oid')->default(0);
            $table->bigInteger('amount_minor')->default(0);
            $table->unsignedTinyInteger('state')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index(['uid', 'oid'], 'idx_bet_points_flow_user_order');
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id', 255)->primary();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
            $table->index('user_id', 'idx_bet_sessions_user');
            $table->index('last_activity', 'idx_bet_sessions_last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('points_flow');
        Schema::dropIfExists('points_balance');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order');
        Schema::dropIfExists('biz_selection');
        Schema::dropIfExists('biz_market');
        Schema::dropIfExists('biz_event');
    }
};
