# ************************************************************
# Sequel Pro SQL dump
# Version 4499
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.7.9)
# Database: phalcon_trade
# Generation Time: 2017-01-14 13:01:52 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table apps
# ------------------------------------------------------------

DROP TABLE IF EXISTS `apps`;

CREATE TABLE `apps` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) DEFAULT '',
  `app_id` varchar(16) DEFAULT '',
  `secret_key` varchar(32) DEFAULT '',
  `notify_url` varchar(512) DEFAULT '',
  `trade_method` varchar(40) DEFAULT '',
  `trade_tip` varchar(1000) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应用';



# Dump of table notify_logs
# ------------------------------------------------------------

DROP TABLE IF EXISTS `notify_logs`;

CREATE TABLE `notify_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction` varchar(32) DEFAULT '0',
  `notify_url` varchar(1000) DEFAULT '',
  `request` text,
  `response` text,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction` (`transaction`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='通知日志';



# Dump of table products
# ------------------------------------------------------------

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(32) NOT NULL DEFAULT '0',
  `package` varchar(64) DEFAULT '',
  `name` varchar(64) DEFAULT '',
  `product_id` varchar(64) DEFAULT '',
  `gateway` varchar(32) DEFAULT '',
  `price` decimal(10,2) unsigned DEFAULT '0.00',
  `currency` varchar(8) DEFAULT '',
  `coin` int(10) unsigned DEFAULT '0',
  `status` tinyint(3) unsigned DEFAULT '1',
  `sort` int(10) unsigned DEFAULT '0',
  `remark` varchar(255) DEFAULT '',
  `image` varchar(1000) DEFAULT '',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table transactions
# ------------------------------------------------------------

DROP TABLE IF EXISTS `transactions`;

CREATE TABLE `transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction` varchar(32) DEFAULT '' COMMENT '订单ID',
  `app_id` varchar(16) DEFAULT '0' COMMENT '应用ID',
  `user_id` varchar(16) DEFAULT '0' COMMENT '账号ID',
  `currency` varchar(3) DEFAULT '' COMMENT '币种',
  `amount` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '金额',
  `amount_usd` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '美元',
  `status` enum('pending','closed','failed','refund','paid','complete','sandbox') DEFAULT 'pending' COMMENT '支付状态',
  `gateway` varchar(16) DEFAULT NULL COMMENT '支付网关',
  `trade_no` varchar(32) DEFAULT NULL COMMENT '网关订单号',
  `product_id` varchar(60) DEFAULT '' COMMENT '产品ID',
  `end_user` varchar(64) DEFAULT '' COMMENT '终端用户标识',
  `ip` varchar(15) DEFAULT '' COMMENT 'IP',
  `uuid` varchar(36) DEFAULT '' COMMENT '唯一设备标识',
  `adid` varchar(40) DEFAULT '' COMMENT '广告追踪标识',
  `device` varchar(32) DEFAULT '' COMMENT '操作系统',
  `channel` varchar(32) DEFAULT '' COMMENT '渠道',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `complete_time` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `transaction` (`transaction`),
  KEY `uuid` (`uuid`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付中心';




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
