CREATE DATABASE IF NOT EXISTS `bet_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `bet_agg`;

-- =============================================================================
-- biz_game
--
-- 本地博彩状态：{@code raw_id} 为 CMS 场次 id；标题、主图/banner、开赛时间等展示数据
-- 由 CMS 或后续独立链路提供，不在本表冗余。
-- winning_selection_ids 在结算时落入；biz_market.game_id 关联本表 id（local key）。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `biz_game` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `raw_id` bigint unsigned NOT NULL COMMENT '外部/CMS game 主键，对应 /api/cms/game/{raw_id}',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 closed, 3 settled',
  `winning_selection_ids` text DEFAULT NULL COMMENT 'JSON 编码的获胜选项 ID 列表',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_biz_game_raw_id` (`raw_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏盘口聚合根；仅本地博彩状态';

-- =============================================================================
-- biz_market / biz_selection（结构未变；仅 schema 注释保持一致）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `biz_market` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `name` varchar(256) NOT NULL DEFAULT '' COMMENT '盘口展示名称',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 suspended, 3 settled',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_biz_market_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='体育盘口';

CREATE TABLE IF NOT EXISTS `biz_selection` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `market_id` bigint unsigned NOT NULL,
  `label` varchar(256) NOT NULL DEFAULT '',
  `current_odds_millis` int unsigned NOT NULL DEFAULT '0' COMMENT '欧洲盘小数赔率 * 1000，整数',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 suspended, 3 settled',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_biz_selection_market` (`market_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘口选项';

-- =============================================================================
-- bet_order（原 `order` 表重命名；删除 checkout_phase / ext_inventory / ext_id
-- 三列——单步下注后这些状态由 BetOrderStatus 状态机覆盖，不再需要。）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `bet_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL DEFAULT '0',
  `idem_key` bigint unsigned NOT NULL COMMENT 'POST /bet/place 幂等键：snowflake 整数（同 uid 下唯一）',
  `total_price` int NOT NULL DEFAULT '0' COMMENT '总下注 stake points',
  `status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'BetOrderStatus enum',
  `points_held` int NOT NULL DEFAULT '0' COMMENT '冻结的 stake（accept 后通常等于 total_price）',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_order_uid_idem_key` (`uid`,`idem_key`),
  KEY `idx_bet_order_user` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='下注订单（单步原子下注：建单 + 冻结 + accept）';

-- =============================================================================
-- order_item（结构未变；oid 现在指向 bet_order.id）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `order_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `oid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `kid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'biz_selection.id',
  `stake_points` int unsigned NOT NULL DEFAULT '0',
  `odds_snapshot` text DEFAULT NULL COMMENT 'JSON 编码；原始赔率与展示上下文，不可依赖 live 表解释历史订单',
  `decimal_odds_millis` int unsigned NOT NULL DEFAULT '0',
  `potential_return_points` bigint unsigned NOT NULL DEFAULT '0' COMMENT '若胜出应收总额（含本金，欧洲盘）',
  `result` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0 pending, 1 win, 2 lose, 3 void',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order_item_oid` (`oid`),
  KEY `idx_order_item_kid` (`kid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单明细（含赔率快照）';

-- =============================================================================
-- settle_job
--
-- Paganini\Batch 任务头表（应用侧表名注入到 paganini PdoBatchJobRepository）。
-- biz_key 形如 "settle:game:<game_id>"；同一 biz_key 仅允许一条未 terminated
-- 的记录，crash 后由 BatchExecutor 按 cursor_offset resume。payload 落 JSON
-- 仅作诊断与 resume 上下文，不参与业务校验。
-- =============================================================================
CREATE TABLE IF NOT EXISTS `settle_job` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `biz_key` varchar(128) NOT NULL,
  `payload` text DEFAULT NULL COMMENT 'JSON-encoded outer-phase payload',
  `total` int unsigned NOT NULL DEFAULT '0',
  `cursor_offset` int unsigned NOT NULL DEFAULT '0' COMMENT '已处理项数（含失败）',
  `success_count` int unsigned NOT NULL DEFAULT '0',
  `failure_count` int unsigned NOT NULL DEFAULT '0',
  `status` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'JobStatus: 0 pending, 1 running, 2 completed, 3 partial, 4 failed',
  `last_error` text DEFAULT NULL,
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_settle_biz_key` (`biz_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='结算批处理任务头（Paganini\\Batch）';

-- =============================================================================
-- points_balance / points_flow（结构未变）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `points_balance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0' COMMENT '可用点数',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_points_bal_user` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏点数账户';

-- 结算路径需要幂等：同一 (oid, state) 不允许重复落账（双扣防御层之一）。
CREATE TABLE IF NOT EXISTS `points_flow` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL DEFAULT '0',
  `oid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `amount` bigint NOT NULL DEFAULT '0',
  `state` tinyint unsigned NOT NULL DEFAULT '0',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bet_points_flow_user_order` (`uid`,`oid`),
  UNIQUE KEY `uni_bet_points_flow_oid_state` (`oid`,`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点数流水（应用层仅追加；审计 + 结算幂等保证）';

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bet_sessions_user` (`user_id`),
  KEY `idx_bet_sessions_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 从旧 schema 迁移到当前版本（运维手动执行；按顺序）
-- =============================================================================
-- 1) 表重命名：order → bet_order
--    RENAME TABLE `order` TO `bet_order`;
-- 2) 删除单步下注后已不再使用的列：
--    ALTER TABLE `bet_order`
--      DROP COLUMN `checkout_phase`,
--      DROP COLUMN `ext_inventory`,
--      DROP COLUMN `ext_id`;
-- 3) biz_game 移除 CMS 冗余列与索引（若已按旧版迁过）：
--    ALTER TABLE `biz_game`
--      DROP KEY `idx_biz_game_status_starts`,
--      DROP COLUMN `ut_cms`,
--      DROP COLUMN `main_media`,
--      DROP COLUMN `banner`,
--      DROP COLUMN `starts_at`,
--      DROP COLUMN `title`;
-- 4) points_flow 增加结算幂等唯一键：
--    ALTER TABLE `points_flow`
--      ADD UNIQUE KEY `uni_bet_points_flow_oid_state` (`oid`,`state`);
--    -- 上线前应先确认历史流水中 (oid, state) 没有重复行（settlement 此前没有
--    --   做幂等约束，理论上一个 game 重新结算会出现重复，需先清理）。
-- 5) 新增 settle_job：执行上方 CREATE TABLE。若库中仍为旧名：RENAME TABLE `bet_settle_job` TO `settle_job`;
-- 6) 若旧库存在 idem_key 表：将各订单行的 idempotency key 回写到 bet_order.idem_key 后
--    添加 UNIQUE KEY uni_bet_order_uid_idem_key (uid, idem_key)，再 DROP TABLE idem_key。
