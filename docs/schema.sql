CREATE DATABASE IF NOT EXISTS `bet_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `bet_agg`;

-- =============================================================================
-- biz_game_group / biz_game_subject / biz_y（分组 ↔ 赛事主体，多对多）
-- biz_x（分组 ↔ 本地赛事，多对多）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `biz_game_group` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(192) NOT NULL COMMENT '对外稳定代号，如 fifa-2026-group',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_biz_game_group_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `biz_game_subject` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) NOT NULL COMMENT '球队/选手等展示名',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `biz_y` (
  `group_id` bigint unsigned NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`group_id`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- biz_game：local key；raw_id = CMS id；双方为赛事主体 FK；结算写入 winning_outcomes（TEXT 存 JSON 数组，synthetic keys）
-- =============================================================================
CREATE TABLE IF NOT EXISTS `biz_game` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `raw_id` bigint unsigned NOT NULL COMMENT '外部/CMS game 主键',
  `side_a_subject_id` bigint unsigned DEFAULT NULL COMMENT '主场侧 → biz_game_subject.id',
  `side_b_subject_id` bigint unsigned DEFAULT NULL COMMENT '客场侧 → biz_game_subject.id',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 closed, 3 settled',
  `winning_outcomes` text DEFAULT NULL COMMENT 'JSON 编码的字符串数组，元素如 home_win / draw / away_win',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_biz_game_raw_id` (`raw_id`),
  KEY `idx_biz_game_side_a` (`side_a_subject_id`),
  KEY `idx_biz_game_side_b` (`side_b_subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `biz_x` (
  `group_id` bigint unsigned NOT NULL COMMENT 'biz_game_group.id',
  `gid` bigint unsigned NOT NULL COMMENT 'biz_game.id',
  PRIMARY KEY (`group_id`,`gid`),
  KEY `idx_biz_x_gid` (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- biz_market：type=MarketType 整型；赔率集中在 odds_millis（TEXT，JSON）；不再使用 biz_selection
-- =============================================================================
CREATE TABLE IF NOT EXISTS `biz_market` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint unsigned NOT NULL,
  `type` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'MarketType: 1 = 胜平负',
  `name` varchar(256) NOT NULL DEFAULT '' COMMENT '盘口展示名称',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 suspended, 3 settled',
  `odds_millis` text DEFAULT NULL COMMENT 'JSON：outcome_code -> 欧洲盘×1000；type=1 时为 home_win / draw / away_win',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_biz_market_game` (`game_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- bet_order / order_item（明细：market_id + selection JSON）
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `oid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `market_id` bigint unsigned NOT NULL,
  `selection` text DEFAULT NULL COMMENT 'JSON 编码选项；type=胜平负时为 {"code":"home_win|draw|away_win"}',
  `stake_points` int unsigned NOT NULL DEFAULT '0',
  `odds_snapshot` text DEFAULT NULL COMMENT 'JSON 编码；下单时上下文',
  `decimal_odds_millis` int unsigned NOT NULL DEFAULT '0',
  `potential_return_points` bigint unsigned NOT NULL DEFAULT '0' COMMENT '若胜出应收总额（含本金，欧洲盘）',
  `result` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '0 pending, 1 win, 2 lose, 3 void',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order_item_oid` (`oid`),
  KEY `idx_order_item_market` (`market_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- settle_job（Paganini Batch）
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- points_balance / points_flow
-- =============================================================================
CREATE TABLE IF NOT EXISTS `points_balance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0' COMMENT '可用点数',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_points_bal_user` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 已移除：`biz_selection`；旧库迁移请自行数据清洗后 DROP。
