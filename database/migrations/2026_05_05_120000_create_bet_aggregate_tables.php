<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        Schema::create('order', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0)->index('idx_bet_order_user');
            $table->integer('total_price')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedSmallInteger('checkout_phase')->default(0);
            $table->boolean('ext_inventory')->default(false);
            $table->string('ext_id', 128)->default('');
            $table->integer('points_held')->default(0);
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_flow');
        Schema::dropIfExists('points_balance');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order');
        Schema::dropIfExists('biz_selection');
        Schema::dropIfExists('biz_market');
        Schema::dropIfExists('biz_game');
    }
};
