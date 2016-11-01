# ************************************************************
# Sequel Pro SQL dump
# Version 4499
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.7.9)
# Database: dataDefault
# Generation Time: 2016-11-01 10:07:37 +0000
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
  `notify_url` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应用';



# Dump of table logsNotice
# ------------------------------------------------------------

DROP TABLE IF EXISTS `logsNotice`;

CREATE TABLE `logsNotice` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction` varchar(32) DEFAULT '0' COMMENT '订单ID',
  `url` varchar(255) DEFAULT '' COMMENT '地址',
  `request` text COMMENT '请求',
  `response` text COMMENT '响应',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00' COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `transaction` (`transaction`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='通知日志';



# Dump of table products
# ------------------------------------------------------------

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` int(10) unsigned NOT NULL DEFAULT '0',
  `package` varchar(32) DEFAULT '',
  `name` varchar(64) DEFAULT '',
  `product_id` varchar(64) DEFAULT '',
  `gateway` varchar(32) DEFAULT '',
  `price` decimal(10,2) unsigned DEFAULT '0.00',
  `currency` varchar(8) DEFAULT '',
  `money` int(10) unsigned DEFAULT '0',
  `status` tinyint(3) unsigned DEFAULT '1',
  `sort` int(10) unsigned DEFAULT '0',
  `remark` varchar(255) DEFAULT '',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  `update_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`),
  KEY `sort` (`app_id`,`gateway`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table transaction
# ------------------------------------------------------------

DROP TABLE IF EXISTS `transaction`;

CREATE TABLE `transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction` varchar(32) DEFAULT '' COMMENT '订单ID',
  `app_id` varchar(16) DEFAULT '0' COMMENT '应用ID',
  `user_id` varchar(16) DEFAULT '0' COMMENT '用户ID',
  `currency` varchar(3) DEFAULT '' COMMENT '货币类型',
  `amount` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '金额',
  `amount_usd` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '美元',
  `status` enum('pending','closed','failed','refund','paid','complete','sandbox') DEFAULT 'pending' COMMENT '支付状态',
  `gateway` varchar(16) DEFAULT NULL COMMENT '支付网关',
  `trade_no` varchar(32) DEFAULT NULL COMMENT '网关订单号',
  `product_id` varchar(60) DEFAULT '' COMMENT '产品ID',
  `end_user` varchar(32) DEFAULT '' COMMENT '终端用户标识',
  `ip` varchar(15) DEFAULT '' COMMENT 'IP',
  `custom` varchar(60) DEFAULT '' COMMENT '自定义内容',
  `uuid` varchar(36) DEFAULT '' COMMENT '唯一设备标识',
  `adid` varchar(40) DEFAULT '' COMMENT '广告追踪标识',
  `device` varchar(32) DEFAULT '' COMMENT '操作系统',
  `channel` varchar(32) DEFAULT '' COMMENT '渠道',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00' COMMENT '创建时间',
  `complete_time` datetime DEFAULT '0000-01-01 00:00:00' COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction` (`transaction`),
  UNIQUE KEY `seq` (`gateway`,`trade_no`),
  KEY `time` (`complete_time`),
  KEY `dim` (`app_id`,`uuid`,`channel`) USING BTREE,
  KEY `user_id` (`app_id`,`user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付中心';




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
