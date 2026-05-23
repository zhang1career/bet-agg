-- --------------------------------------------------------
-- Host:                         39.107.60.82
-- Server version:               5.7.44-log - MySQL Community Server (GPL)
-- Server OS:                    Linux
-- HeidiSQL Version:             11.2.0.6213
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for bet_agg
CREATE DATABASE IF NOT EXISTS `bet_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `bet_agg`;

-- Dumping structure for table bet_agg.bet_order
CREATE TABLE IF NOT EXISTS `bet_order` (
                                           `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
    `idem_key` bigint(20) unsigned DEFAULT NULL,
    `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-recorded(open), 2-cancelled, 3-won, 4-lost, 5-void, 6-settlement failed',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_order_idem` (`idem_key`),
    KEY `idx_bet_order_user` (`uid`) USING BTREE,
    KEY `idx_bet_order_status` (`status`,`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game
CREATE TABLE IF NOT EXISTS `biz_game` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `raw_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'cms game ID',
    `side_a_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '主场侧',
    `side_b_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '客场侧',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-closed, 3-settled, 4-pending settlement',
    `settle_outcomes` text COLLATE utf8mb4_unicode_ci COMMENT 'json',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_game_raw` (`raw_id`),
    KEY `idx_bet_game_side_a` (`side_a_subject_id`),
    KEY `idx_bet_game_side_b` (`side_b_subject_id`),
    KEY `idx_bet_game_status` (`status`,`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000010 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务竞赛';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game_group
CREATE TABLE IF NOT EXISTS `biz_game_group` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `code` varchar(192) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '对外稳定代号，如 fifa-2026-group',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uni_game_group_code` (`code`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事分组';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game_subject
CREATE TABLE IF NOT EXISTS `biz_game_subject` (
                                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '球队/选手等展示名',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000018 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事主体';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_market
CREATE TABLE IF NOT EXISTS `biz_market` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `gid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'game ID',
    `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '盘口名称',
    `type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '盘口类型，0 = 胜平负',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-suspended, 3-settled',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_bet_market_game` (`gid`) USING BTREE,
    KEY `idx_bet_market_status` (`status`,`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000010 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务盘口';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_market_quote
CREATE TABLE IF NOT EXISTS `biz_market_quote` (
  `mid` bigint(20) unsigned NOT NULL COMMENT 'market ID',
  `outcome_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'home_win | draw | away_win',
  `pick_count` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '累计有效预测数',
  `share_bp` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '占比万分比 0-10000',
  `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
  PRIMARY KEY (`mid`,`outcome_code`) USING BTREE,
  KEY `idx_market_quote_ut` (`ut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='盘口当前预测分布快照';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_market_quote_hist
CREATE TABLE IF NOT EXISTS `biz_market_quote_hist` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mid` bigint(20) unsigned NOT NULL COMMENT 'market ID',
  `bucket_start` bigint(20) unsigned NOT NULL COMMENT '桶起始 Unix ms',
  `interval_code` tinyint(3) unsigned NOT NULL COMMENT '1=1h, 2=1d',
  `outcome_code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pick_count` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '桶结束时累计数',
  `share_bp` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '桶结束时占比万分比',
  `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uni_quote_hist_bucket` (`mid`,`interval_code`,`bucket_start`,`outcome_code`),
  KEY `idx_quote_hist_mid_time` (`mid`,`interval_code`,`bucket_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='盘口预测分布历史（按时间桶）';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.order_item
CREATE TABLE IF NOT EXISTS `order_item` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'order ID',
    `mid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'market ID',
    `selection` text COLLATE utf8mb4_unicode_ci COMMENT 'json',
    `pick_label` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'display label at submit time',
    `result` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-win, 2-lose, 3-void',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_bet_order_item_order` (`oid`) USING BTREE,
    KEY `idx_bet_order_item_market` (`mid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10000013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单元素';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.points_balance
CREATE TABLE IF NOT EXISTS `points_balance` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
    `balance` bigint(20) NOT NULL DEFAULT '0',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_points_bal_user` (`uid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10000004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='积分账户';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.points_flow
CREATE TABLE IF NOT EXISTS `points_flow` (
                                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
    `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'order ID',
    `amount` bigint(20) NOT NULL DEFAULT '0',
    `state` tinyint(3) unsigned NOT NULL DEFAULT '0',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_points_flow_order_state` (`oid`,`state`),
    KEY `idx_bet_points_flow_user_order` (`uid`,`oid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10000006 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='积分流水';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.sessions
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.settle_job
CREATE TABLE IF NOT EXISTS `settle_job` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `biz_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
    `payload` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON-encoded outer-phase payload',
    `total` int(10) unsigned NOT NULL DEFAULT '0',
    `cursor_offset` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已处理项数（含失败）',
    `success_count` int(10) unsigned NOT NULL DEFAULT '0',
    `failure_count` int(10) unsigned NOT NULL DEFAULT '0',
    `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT 'JobStatus: 0 pending, 1 running, 2 completed, 3 partial, 4 failed',
    `last_error` text COLLATE utf8mb4_unicode_ci,
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uni_bet_settle_job_biz` (`biz_key`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10000006 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='结算批处理任务头（Paganini\\Batch）';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.x
CREATE TABLE IF NOT EXISTS `x` (
                                   `pid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'group ID',
    `gid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'game ID',
    PRIMARY KEY (`pid`,`gid`) USING BTREE,
    KEY `idx_x_game` (`gid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='game_group - game';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.y
CREATE TABLE IF NOT EXISTS `y` (
                                   `pid` bigint(20) unsigned NOT NULL COMMENT 'group ID',
    `sid` bigint(20) unsigned NOT NULL COMMENT 'subject ID',
    PRIMARY KEY (`pid`,`sid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='game_group - subject';

-- Data exporting was unselected.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
