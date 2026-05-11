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
    `total_price` int(11) NOT NULL DEFAULT '0' COMMENT '总价，单位：分',
    `points_held` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'checkout 后记入的已占用 stake（通常为 total_price）',
    `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-paid, 2-cancelled',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_order_idem` (`idem_key`),
    KEY `idx_bet_order_uid_id` (`uid`,`id`) USING BTREE COMMENT 'user order list: WHERE uid ORDER BY id',
    KEY `idx_bet_order_status_id` (`status`,`id`) USING BTREE COMMENT 'settlement: WHERE status IN (...) ORDER BY id'
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game
CREATE TABLE IF NOT EXISTS `biz_game` (
                                          `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `raw_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'cms game ID',
    `side_a_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'A 方赛事主体',
    `side_b_subject_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'B 方赛事主体',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-closed, 3-settled, 4-pending settlement',
    `settle_outcomes` text COLLATE utf8mb4_unicode_ci COMMENT 'json',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_bet_game_raw` (`raw_id`),
    KEY `idx_bet_game_side_a` (`side_a_subject_id`),
    KEY `idx_bet_game_side_b` (`side_b_subject_id`),
    KEY `idx_biz_game_status_id` (`status`,`id`) USING BTREE COMMENT 'catalog/admin: filter by status, sort by id'
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务竞赛';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game_group
CREATE TABLE IF NOT EXISTS `biz_game_group` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `code` varchar(192) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '对外稳定代号，如 fifa-2026-group',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uni_game_group_code` (`code`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事分组';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_game_subject
CREATE TABLE IF NOT EXISTS `biz_game_subject` (
                                                  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '球队/选手等展示名',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事主体';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_market
CREATE TABLE IF NOT EXISTS `biz_market` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `game_id` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'event ID',
    `name` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '盘口名称',
    `type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '盘口类型，1 = 胜平负',
    `odds_millis` text COLLATE utf8mb4_unicode_ci COMMENT '赔率，json编码，x1000',
    `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0-init, 1-open, 2-suspended, 3-settled',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_bet_sport_market_game` (`game_id`) USING BTREE,
    KEY `idx_biz_market_status_id` (`status`,`id`) USING BTREE COMMENT 'catalog: WHERE status IN (...) ORDER BY id'
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='业务盘口';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_x
CREATE TABLE IF NOT EXISTS `biz_x` (
                                       `group_id` bigint(20) unsigned NOT NULL DEFAULT '0',
    `gid` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`group_id`,`gid`),
    KEY `idx_x_game` (`gid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事与分组关联（pivot）';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.biz_y
CREATE TABLE IF NOT EXISTS `biz_y` (
                                       `group_id` bigint(20) unsigned NOT NULL,
    `subject_id` bigint(20) unsigned NOT NULL,
    PRIMARY KEY (`group_id`,`subject_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='赛事组-赛事主体关系';

-- Data exporting was unselected.

-- Dumping structure for table bet_agg.order_item
CREATE TABLE IF NOT EXISTS `order_item` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'order ID',
    `market_id` bigint(20) unsigned NOT NULL DEFAULT '0',
    `selection` text COLLATE utf8mb4_unicode_ci COMMENT 'json',
    `stake_points` int(10) unsigned NOT NULL DEFAULT '0',
    `odds_snapshot` text COLLATE utf8mb4_unicode_ci COMMENT '下单时上下文，json编码',
    `decimal_odds_millis` int(10) unsigned NOT NULL DEFAULT '0',
    `potential_return_points` bigint(20) unsigned NOT NULL DEFAULT '0',
    `result` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-win, 2-lose, 3-void',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_bet_order_item_order` (`oid`) USING BTREE,
    KEY `idx_bet_order_item_market` (`market_id`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单元素';

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
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='积分账户';

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
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='积分流水';

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
    ) ENGINE=InnoDB AUTO_INCREMENT=10000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='结算批处理任务头（Paganini\\Batch）';

-- Data exporting was unselected.

/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
