<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** SQLite DDL for feature tests ({@code docs/schema.sql} subset). */
final class BetAggSchema
{
    public static function apply(): void
    {
        $pdo = DB::connection()->getPdo();

        foreach (self::statements() as $sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * @return list<string>
     */
    private static function statements(): array
    {
        return [
            <<<'SQL'
CREATE TABLE IF NOT EXISTS bet_order (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uid INTEGER NOT NULL DEFAULT 0,
  idem_key INTEGER,
  status INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_bet_order_idem ON bet_order (idem_key)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS order_item (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  oid INTEGER NOT NULL DEFAULT 0,
  mid INTEGER NOT NULL DEFAULT 0,
  selection TEXT,
  pick_label TEXT NOT NULL DEFAULT '',
  result INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS points_balance (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uid INTEGER NOT NULL DEFAULT 0,
  balance INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_points_balance_uid ON points_balance (uid)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS points_flow (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uid INTEGER NOT NULL DEFAULT 0,
  oid INTEGER NOT NULL DEFAULT 0,
  amount INTEGER NOT NULL DEFAULT 0,
  state INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_points_flow_oid_state ON points_flow (oid, state)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_game (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  raw_id INTEGER NOT NULL DEFAULT 0,
  side_a_subj_id INTEGER,
  side_b_subj_id INTEGER,
  status INTEGER NOT NULL DEFAULT 0,
  settle_outcomes TEXT,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_bet_game_raw ON biz_game (raw_id)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_game_group (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_game_group_code ON biz_game_group (code)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_game_subject (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL DEFAULT '',
  icon TEXT NOT NULL DEFAULT '',
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_market (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  gid INTEGER NOT NULL DEFAULT 0,
  name TEXT NOT NULL DEFAULT '',
  type INTEGER NOT NULL DEFAULT 0,
  status INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_market_quote (
  mid INTEGER NOT NULL,
  outcome_code TEXT NOT NULL,
  pick_count INTEGER NOT NULL DEFAULT 0,
  share_bp INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (mid, outcome_code)
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS biz_market_quote_hist (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  mid INTEGER NOT NULL,
  bucket_start INTEGER NOT NULL,
  interval_code INTEGER NOT NULL,
  outcome_code TEXT NOT NULL,
  pick_count INTEGER NOT NULL DEFAULT 0,
  share_bp INTEGER NOT NULL DEFAULT 0,
  ct INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_quote_hist_bucket ON biz_market_quote_hist (mid, interval_code, bucket_start, outcome_code)',
            <<<'SQL'
CREATE TABLE IF NOT EXISTS x (
  pid INTEGER NOT NULL DEFAULT 0,
  gid INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (pid, gid)
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS y (
  pid INTEGER NOT NULL,
  sid INTEGER NOT NULL,
  PRIMARY KEY (pid, sid)
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS settle_job (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  biz_key TEXT NOT NULL,
  payload TEXT,
  total INTEGER NOT NULL DEFAULT 0,
  cursor_offset INTEGER NOT NULL DEFAULT 0,
  success_count INTEGER NOT NULL DEFAULT 0,
  failure_count INTEGER NOT NULL DEFAULT 0,
  status INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  ct INTEGER NOT NULL DEFAULT 0,
  ut INTEGER NOT NULL DEFAULT 0
)
SQL,
            'CREATE UNIQUE INDEX IF NOT EXISTS uni_bet_settle_job_biz ON settle_job (biz_key)',
        ];
    }
}
