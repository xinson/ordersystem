<?php
$db->execute("
DROP TABLE IF EXISTS `received_history`;
CREATE TABLE `received_history` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `command` varchar(300) NOT NULL DEFAULT '' COMMENT '接口地址',
 `request_data` text COMMENT '请求数据',
 `response_data` text COMMENT '响应数据',
 `created_at` int(10) DEFAULT NULL COMMENT '请求时间',
 `response_at` int(10) DEFAULT NULL COMMENT '响应时间',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='接口接收请求记录';
");
