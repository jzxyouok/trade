# ************************************************************
# Sequel Pro SQL dump
# Version 4499
#
# http://www.sequelpro.com/
# https://github.com/sequelpro/sequelpro
#
# Host: 127.0.0.1 (MySQL 5.7.9)
# Database: phalcon_manage
# Generation Time: 2017-01-03 10:42:49 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table logs_login
# ------------------------------------------------------------

DROP TABLE IF EXISTS `logs_login`;

CREATE TABLE `logs_login` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT '0',
  `ip` varchar(15) DEFAULT '',
  `location` varchar(32) DEFAULT '',
  `user_agent` varchar(225) DEFAULT '',
  `referer` text,
  `result` tinyint(4) DEFAULT '0',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志';



# Dump of table resources
# ------------------------------------------------------------

DROP TABLE IF EXISTS `resources`;

CREATE TABLE `resources` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app` varchar(32) DEFAULT '',
  `name` varchar(32) DEFAULT '',
  `resource` varchar(64) DEFAULT '',
  `type` enum('menu','node') DEFAULT NULL,
  `parent` int(11) DEFAULT '0',
  `sort` int(11) DEFAULT '0',
  `status` tinyint(3) DEFAULT '1',
  `icon` varchar(64) DEFAULT '',
  `remark` varchar(64) DEFAULT '',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  `update_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `appResource` (`app`,`resource`,`sort`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限部分 - 资源';

LOCK TABLES `resources` WRITE;
/*!40000 ALTER TABLE `resources` DISABLE KEYS */;

INSERT INTO `resources` (`id`, `app`, `name`, `resource`, `type`, `parent`, `sort`, `status`, `icon`, `remark`, `create_time`, `update_time`)
VALUES
	(1000,'','用户管理','/users/index','menu',0,0,1,'','','0000-01-01 00:00:00','0000-01-01 00:00:00'),
	(1001,'','角色管理','/roles/index','menu',0,0,1,'','','0000-01-01 00:00:00','0000-01-01 00:00:00'),
	(1002,'','资源管理','/resources/index','menu',0,0,1,'','','0000-01-01 00:00:00','0000-01-01 00:00:00');

/*!40000 ALTER TABLE `resources` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table role_resource
# ------------------------------------------------------------

DROP TABLE IF EXISTS `role_resource`;

CREATE TABLE `role_resource` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT '0',
  `resource_id` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `resource_id` (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限部分 - 角色&资源';



# Dump of table roles
# ------------------------------------------------------------

DROP TABLE IF EXISTS `roles`;

CREATE TABLE `roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) DEFAULT '',
  `parent` int(10) DEFAULT '0',
  `remark` varchar(255) DEFAULT '',
  `status` tinyint(3) DEFAULT '1',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  `update_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限部分 - 角色';

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;

INSERT INTO `roles` (`id`, `name`, `parent`, `remark`, `status`, `create_time`, `update_time`)
VALUES
	(100,'管理员',0,'',1,'0000-01-01 00:00:00','0000-01-01 00:00:00');

/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;


# Dump of table tickets
# ------------------------------------------------------------

DROP TABLE IF EXISTS `tickets`;

CREATE TABLE `tickets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT '0',
  `ticket` varchar(255) DEFAULT '',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `ticket` (`ticket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table user_role
# ------------------------------------------------------------

DROP TABLE IF EXISTS `user_role`;

CREATE TABLE `user_role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT '0',
  `role_id` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限部分 - 用户&角色';



# Dump of table users
# ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(32) DEFAULT '',
  `password` varchar(225) DEFAULT '',
  `name` varchar(64) DEFAULT '',
  `status` tinyint(3) DEFAULT '1',
  `phone` varchar(20) DEFAULT '',
  `secret_key` varchar(64) DEFAULT '',
  `create_time` datetime DEFAULT '0000-01-01 00:00:00',
  `update_time` datetime DEFAULT '0000-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限部分 - 用户';

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;

INSERT INTO `users` (`id`, `username`, `password`, `name`, `status`, `phone`, `secret_key`, `create_time`, `update_time`)
VALUES
	(10000,'joe@xxtime.com','','Joe Chu',1,'','','0000-01-01 00:00:00','0000-01-01 00:00:00'),
	(10001,'demo@xxtime.com','','Demo',1,'','','0000-01-01 00:00:00','0000-01-01 00:00:00');

/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;



/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
