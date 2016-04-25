<?php
/**
直接写sql语句
$db->execute("ALTER TABLE `user` ADD `password` VARCHAR(255) NULL DEFAULT NULL COMMENT '支付密码，NULL时代表未设置' AFTER `username`;");
 *
 */
