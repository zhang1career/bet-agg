<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bet_order') || ! Schema::hasColumn('bet_order', 'saga_idem_key')) {
            return;
        }

        Schema::table('bet_order', function (Blueprint $table): void {
            $table->dropUnique('uni_bet_order_saga_idem');
            $table->dropUnique('uni_bet_order_tcc_idem');
            $table->dropIndex('idx_bet_order_tx');
        });

        Schema::table('bet_order', function (Blueprint $table): void {
            $table->dropColumn(['saga_idem_key', 'tcc_idem_key', 'tid']);
        });
    }

    public function down(): void
    {
        //
    }
};
