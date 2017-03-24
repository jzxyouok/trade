-- phpMyAdmin SQL Dump
-- version 4.5.1
-- http://www.phpmyadmin.net
--
-- Host: 127.0.0.1
-- Generation Time: 2017-03-14 09:00:22
-- 服务器版本： 5.7.9
-- PHP Version: 5.6.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `phalcon_trade`
--

-- --------------------------------------------------------

--
-- 表的结构 `apps`
--

CREATE TABLE `apps` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(64) DEFAULT '',
  `app_id` varchar(16) DEFAULT '',
  `secret_key` varchar(32) DEFAULT '',
  `notify_url` varchar(512) DEFAULT '',
  `trade_tip` varchar(1000) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应用';

-- --------------------------------------------------------

--
-- 表的结构 `gateways`
--

CREATE TABLE `gateways` (
  `id` int(11) UNSIGNED NOT NULL,
  `app_id` varchar(16) DEFAULT '' COMMENT '应用ID',
  `type` enum('wallet','card','telecom') DEFAULT 'wallet' COMMENT '付费类型:钱包 预付卡 运营商',
  `sandbox` tinyint(3) DEFAULT '0' COMMENT '是否沙箱测试',
  `parent` int(10) DEFAULT '0' COMMENT '父级ID',
  `sort` int(10) DEFAULT '0' COMMENT '排序',
  `name` varchar(64) DEFAULT '' COMMENT '名称',
  `remark` varchar(125) DEFAULT '' COMMENT '备注',
  `gateway` varchar(16) DEFAULT '' COMMENT '网关',
  `sub` varchar(32) DEFAULT '' COMMENT '子网关',
  `currency` varchar(32) DEFAULT '' COMMENT '货币',
  `tips` varchar(1000) DEFAULT '' COMMENT '提示信息'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值网关配置';

-- --------------------------------------------------------

--
-- 表的结构 `notify_logs`
--

CREATE TABLE `notify_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction` varchar(32) DEFAULT '0',
  `notify_url` varchar(1000) DEFAULT '',
  `request` text,
  `response` text,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='通知日志';

-- --------------------------------------------------------

--
-- 表的结构 `products`
--

CREATE TABLE `products` (
  `id` int(11) UNSIGNED NOT NULL,
  `app_id` varchar(32) NOT NULL DEFAULT '0',
  `package` varchar(64) DEFAULT '',
  `name` varchar(64) DEFAULT '',
  `product_id` varchar(64) DEFAULT '',
  `gateway` varchar(32) DEFAULT '',
  `price` decimal(10,2) UNSIGNED DEFAULT '0.00',
  `currency` varchar(8) DEFAULT '',
  `coin` int(10) UNSIGNED DEFAULT '0',
  `status` tinyint(3) UNSIGNED DEFAULT '1',
  `sort` int(10) UNSIGNED DEFAULT '0',
  `remark` varchar(255) DEFAULT '',
  `image` varchar(1000) DEFAULT '',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `transactions`
--

CREATE TABLE `transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaction` varchar(32) DEFAULT '' COMMENT '订单ID',
  `app_id` varchar(16) DEFAULT '0' COMMENT '应用ID',
  `user_id` varchar(16) DEFAULT '0' COMMENT '账号ID',
  `currency` varchar(3) DEFAULT '' COMMENT '币种',
  `amount` decimal(10,2) UNSIGNED DEFAULT '0.00' COMMENT '金额',
  `amount_usd` decimal(10,2) UNSIGNED DEFAULT '0.00' COMMENT '美元',
  `status` enum('pending','closed','failed','refund','paid','complete','sandbox') DEFAULT 'pending' COMMENT '支付状态',
  `gateway` enum('apple','google','alipay','weixin','paypal','paymentwall','mycard','mol','unipin','others') DEFAULT 'others' COMMENT '支付网关',
  `product_id` varchar(60) DEFAULT '' COMMENT '产品ID',
  `custom` varchar(64) DEFAULT '' COMMENT '自定义',
  `ip` varchar(15) DEFAULT '' COMMENT 'IP',
  `uuid` varchar(36) DEFAULT '' COMMENT '唯一设备标识',
  `adid` varchar(40) DEFAULT '' COMMENT '广告追踪标识',
  `device` varchar(32) DEFAULT '' COMMENT '操作系统',
  `channel` varchar(32) DEFAULT '' COMMENT '渠道',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `complete_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '完成时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付中心';

-- --------------------------------------------------------

--
-- 表的结构 `trans_more`
--

CREATE TABLE `trans_more` (
  `id` int(11) UNSIGNED NOT NULL,
  `trans_id` varchar(32) DEFAULT '',
  `gateway` enum('apple','google','alipay','weixin','paypal','paymentwall','mycard','mol','other') DEFAULT 'other',
  `trade_no` varchar(32) DEFAULT NULL,
  `key_string` varchar(255) DEFAULT '',
  `data` text,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apps`
--
ALTER TABLE `apps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `app_id` (`app_id`);

--
-- Indexes for table `gateways`
--
ALTER TABLE `gateways`
  ADD PRIMARY KEY (`id`),
  ADD KEY `app_id` (`app_id`);

--
-- Indexes for table `notify_logs`
--
ALTER TABLE `notify_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction` (`transaction`) USING BTREE;

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction` (`transaction`),
  ADD KEY `uuid` (`uuid`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `trans_more`
--
ALTER TABLE `trans_more`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trade_no` (`trade_no`,`gateway`),
  ADD KEY `trans_id` (`trans_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `apps`
--
ALTER TABLE `apps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- 使用表AUTO_INCREMENT `gateways`
--
ALTER TABLE `gateways`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- 使用表AUTO_INCREMENT `notify_logs`
--
ALTER TABLE `notify_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- 使用表AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- 使用表AUTO_INCREMENT `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- 使用表AUTO_INCREMENT `trans_more`
--
ALTER TABLE `trans_more`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
