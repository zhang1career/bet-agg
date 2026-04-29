<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bet_order_line');
        Schema::dropIfExists('bet_order');
        Schema::dropIfExists('sport_event_result');
        Schema::dropIfExists('sport_selection');
        Schema::dropIfExists('sport_market');
        Schema::dropIfExists('sport_event');
        Schema::dropIfExists('order_item');
        Schema::dropIfExists('order');
        Schema::dropIfExists('product_inventory');
        Schema::dropIfExists('product_price');

        Schema::create('sport_event', function (Blueprint $table) {
            $table->id();
            $table->string('name', 512);
            $table->unsignedBigInteger('starts_at')->default(0)->comment('Unix ms');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 open, 2 closed, 3 settled');
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
        });

        Schema::create('sport_market', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('market_type', 128)->default('');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 open, 2 suspended, 3 settled');
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('event_id', 'idx_bet_sport_market_event');
        });

        Schema::create('sport_selection', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->string('label', 256)->default('');
            $table->unsignedInteger('current_odds_millis')->default(0)->comment('Decimal odds * 1000, int');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 open, 2 suspended, 3 settled');
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('market_id', 'idx_bet_sport_selection_market');
        });

        Schema::create('sport_event_result', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->json('winning_selection_ids');
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->unique('event_id', 'uni_bet_event_result_event');
        });

        Schema::create('bet_order', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('uid')->default(0);
            $table->integer('total_price')->default(0)->comment('Total stake points (integer)');
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

        Schema::create('bet_order_line', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('oid')->default(0);
            $table->unsignedBigInteger('selection_id')->default(0);
            $table->unsignedInteger('stake_points')->default(0);
            $table->json('odds_snapshot')->nullable();
            $table->unsignedInteger('decimal_odds_millis')->default(0);
            $table->unsignedBigInteger('potential_return_points')->default(0);
            $table->unsignedTinyInteger('line_result')->nullable()->comment('null pending, 1 win, 2 lose, 3 void');
            $table->unsignedBigInteger('ct')->default(0);
            $table->unsignedBigInteger('ut')->default(0);
            $table->index('oid', 'idx_bet_order_line_order');
            $table->index('selection_id', 'idx_bet_order_line_selection');
        });

        if (! Schema::hasTable('points_balance')) {
            Schema::create('points_balance', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('uid');
                $table->bigInteger('balance_minor')->default(0);
                $table->unsignedBigInteger('ct')->default(0);
                $table->unsignedBigInteger('ut')->default(0);
                $table->unique('uid', 'uni_bet_points_bal_user');
            });
        }

        if (! Schema::hasTable('points_flow')) {
            Schema::create('points_flow', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('uid')->default(0);
                $table->unsignedBigInteger('oid')->default(0);
                $table->bigInteger('amount_minor')->default(0);
                $table->unsignedTinyInteger('state')->default(0);
                $table->string('tcc_idem_key', 64)->nullable();
                $table->unsignedBigInteger('ct')->default(0);
                $table->unsignedBigInteger('ut')->default(0);
                $table->unique('tcc_idem_key', 'uni_bet_points_flow_tcc_idem');
                $table->index(['uid', 'oid'], 'idx_bet_points_flow_user_order');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bet_order_line');
        Schema::dropIfExists('bet_order');
        Schema::dropIfExists('sport_event_result');
        Schema::dropIfExists('sport_selection');
        Schema::dropIfExists('sport_market');
        Schema::dropIfExists('sport_event');
    }
};
