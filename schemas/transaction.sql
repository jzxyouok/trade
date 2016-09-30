# ************************************************************
# Sequel Pro SQL dump
# Version 4499
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.7.9)
# Database: XXTIME
# Generation Time: 2016-09-30 08:42:09 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table transaction
# ------------------------------------------------------------

DROP TABLE IF EXISTS `transaction`;

CREATE TABLE `transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `transaction` varchar(32) DEFAULT '' COMMENT '订单ID',
  `appID` varchar(16) DEFAULT '0' COMMENT '应用ID',
  `userID` varchar(16) DEFAULT '0' COMMENT '用户ID',
  `amount` decimal(10,2) unsigned DEFAULT '0.00' COMMENT '金额',
  `currency` varchar(3) DEFAULT '' COMMENT '货币类型',
  `amountOri` decimal(10,2) DEFAULT '0.00' COMMENT '原始金额',
  `currencyOri` varchar(3) DEFAULT '' COMMENT '原始币别',
  `status` enum('pending','closed','failed','refund','paid','complete','sandbox') DEFAULT 'pending' COMMENT '支付状态',
  `gateway` varchar(16) DEFAULT NULL COMMENT '支付网关',
  `seq` varchar(32) DEFAULT NULL COMMENT '网关订单号',
  `productID` varchar(60) DEFAULT '' COMMENT '产品ID',
  `subject` varchar(255) DEFAULT '' COMMENT '订单标题',
  `endUser` varchar(32) DEFAULT '' COMMENT '终端用户标识',
  `ip` varchar(15) DEFAULT '' COMMENT 'IP',
  `extra` varchar(60) DEFAULT '' COMMENT '拓展字段',
  `uuid` varchar(36) DEFAULT '' COMMENT '唯一设备标识',
  `idfa` varchar(40) DEFAULT '' COMMENT '广告追踪标识',
  `os` varchar(32) DEFAULT '' COMMENT '操作系统',
  `channel` varchar(32) DEFAULT '' COMMENT '渠道',
  `createTime` datetime DEFAULT '0000-01-01 00:00:00' COMMENT '创建时间',
  `completeTime` datetime DEFAULT '0000-01-01 00:00:00' COMMENT '完成时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction` (`transaction`),
  UNIQUE KEY `seq` (`gateway`,`seq`),
  KEY `time` (`completeTime`),
  KEY `userID` (`appID`,`userID`) USING BTREE,
  KEY `dim` (`appID`,`uuid`,`channel`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付中心';




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
