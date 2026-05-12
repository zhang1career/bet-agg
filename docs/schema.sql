-- bet_agg schema (prediction + points tables; settlement deltas on points_balance / points_flow)
-- MySQL 5.7+ / utf8mb4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

CREATE DATABASE IF NOT EXISTS `bet_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `bet_agg`;

CREATE TABLE IF NOT EXISTS `bet_order` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
  `idem_key` bigint(20) unsigned DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-recorded(open), 2-cancelled, 3-won, 4-lost, 5-void, 6-settlement failed',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_bet_order_idem` (`idem_key`),
  KEY `idx_bet_order_uid_id` (`uid`,`id`) USING BTREE,
  KEY `idx_bet_order_status_id` (`status`,`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Prediction submission (no stake)';

CREATE TABLE IF NOT EXISTS `order_item` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `mid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'biz_market.id',
  `selection` text COLLATE utf8mb4_unicode_ci COMMENT 'json e.g. {"code":"home_win"}',
  `pick_label` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'display label at submit time',
  `result` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-win, 2-lose, 3-void',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_order_item_oid` (`oid`) USING BTREE,
  KEY `idx_order_item_mid` (`mid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='One line per prediction order';

CREATE TABLE IF NOT EXISTS `points_balance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
  `balance` bigint(20) NOT NULL DEFAULT '0' COMMENT 'user points score (non-currency in current flows)',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_points_balance_uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='User points balance';

CREATE TABLE IF NOT EXISTS `points_flow` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'bet_order.id',
  `amount` bigint(20) NOT NULL DEFAULT '0' COMMENT 'signed change applied to balance',
  `state` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '1=win credit, 2=loss debit',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_points_flow_oid_state` (`oid`,`state`),
  KEY `idx_points_flow_uid` (`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='Settlement-driven points ledger';

CREATE TABLE IF NOT EXISTS `biz_game` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `raw_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'cms game ID',
  `side_a_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `side_b_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-closed, 3-settled, 4-pending settlement',
  `settle_outcomes` text COLLATE utf8mb4_unicode_ci COMMENT 'json winners/voids',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_bet_game_raw` (`raw_id`),
  KEY `idx_bet_game_side_a` (`side_a_subject_id`),
  KEY `idx_bet_game_side_b` (`side_b_subject_id`),
  KEY `idx_biz_game_status_id` (`status`,`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务竞赛';

CREATE TABLE IF NOT EXISTS `biz_game_group` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(192) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_game_group_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事分组';

CREATE TABLE IF NOT EXISTS `biz_game_subject` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事主体';

CREATE TABLE IF NOT EXISTS `biz_market` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '1 = 胜平负',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-suspended, 3-settled',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_bet_sport_market_gid` (`gid`) USING BTREE,
  KEY `idx_biz_market_status_id` (`status`,`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务盘口（无赔率列）';

CREATE TABLE IF NOT EXISTS `x` (
  `pid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `gid` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`,`gid`),
  KEY `idx_x_game` (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `y` (
  `pid` bigint(20) unsigned NOT NULL,
  `sid` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`pid`,`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_bet_sessions_user` (`user_id`) USING BTREE,
  KEY `idx_bet_sessions_last_activity` (`last_activity`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settle_job` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `biz_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON-encoded outer-phase payload',
  `total` int(10) unsigned NOT NULL DEFAULT '0',
  `cursor_offset` int(10) unsigned NOT NULL DEFAULT '0',
  `success_count` int(10) unsigned NOT NULL DEFAULT '0',
  `failure_count` int(10) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uni_bet_settle_job_biz` (`biz_key`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='结算批处理任务头';

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
