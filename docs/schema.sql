CREATE DATABASE IF NOT EXISTS `bet_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `bet_agg`;

CREATE TABLE IF NOT EXISTS `sport_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(512) NOT NULL,
  `starts_at` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'Unix ms',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 closed, 3 settled',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='体育赛事';

CREATE TABLE IF NOT EXISTS `sport_market` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `market_type` varchar(128) NOT NULL DEFAULT '',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 suspended, 3 settled',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bet_sport_market_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='体育盘口';

CREATE TABLE IF NOT EXISTS `sport_selection` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `market_id` bigint unsigned NOT NULL,
  `label` varchar(256) NOT NULL DEFAULT '',
  `current_odds_millis` int unsigned NOT NULL DEFAULT '0' COMMENT '欧洲盘小数赔率 * 1000，整数',
  `status` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1 open, 2 suspended, 3 settled',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bet_sport_selection_market` (`market_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盘口选项';

CREATE TABLE IF NOT EXISTS `sport_event_result` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `winning_selection_ids` json NOT NULL COMMENT '内部录入的获胜选项 ID 列表',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_event_result_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛果（内部为准）';

CREATE TABLE IF NOT EXISTS `bet_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL DEFAULT '0',
  `total_price` int NOT NULL DEFAULT '0' COMMENT '总下注 stake points',
  `status` tinyint unsigned NOT NULL DEFAULT '0',
  `checkout_phase` smallint unsigned NOT NULL DEFAULT '0',
  `ext_inventory` tinyint(1) NOT NULL DEFAULT '0',
  `ext_id` varchar(128) NOT NULL DEFAULT '',
  `points_deduct_minor` int NOT NULL DEFAULT '0',
  `cash_payable_minor` int NOT NULL DEFAULT '0',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bet_order_user` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='下注订单';

CREATE TABLE IF NOT EXISTS `bet_order_line` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `oid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `selection_id` bigint unsigned NOT NULL DEFAULT '0',
  `stake_points` int unsigned NOT NULL DEFAULT '0',
  `odds_snapshot` json DEFAULT NULL COMMENT '原始赔率与展示上下文，不可依赖 live 表解释历史订单',
  `decimal_odds_millis` int unsigned NOT NULL DEFAULT '0',
  `potential_return_points` bigint unsigned NOT NULL DEFAULT '0' COMMENT '若胜出应收总额（含本金，欧洲盘）',
  `line_result` tinyint unsigned DEFAULT NULL COMMENT 'null pending, 1 win, 2 lose, 3 void',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_bet_order_line_order` (`oid`),
  KEY `idx_bet_order_line_selection` (`selection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单明细（含赔率快照）';

CREATE TABLE IF NOT EXISTS `points_balance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL,
  `balance_minor` bigint NOT NULL DEFAULT '0',
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_points_bal_user` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='游戏点数账户';

CREATE TABLE IF NOT EXISTS `points_flow` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint unsigned NOT NULL DEFAULT '0',
  `oid` bigint unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `amount_minor` bigint NOT NULL DEFAULT '0',
  `state` tinyint unsigned NOT NULL DEFAULT '0',
  `tcc_idem_key` varchar(64) DEFAULT NULL,
  `ct` bigint unsigned NOT NULL DEFAULT '0',
  `ut` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_points_flow_tcc_idem` (`tcc_idem_key`),
  KEY `idx_bet_points_flow_user_order` (`uid`,`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点数流水（应用层仅追加；审计用）';

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
