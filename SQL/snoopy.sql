CREATE TABLE `activity_snoopy_verify_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '验证码id',
  `verify_code` int(9) unsigned NOT NULL COMMENT '验证码序列号,是一个6位数字序列',
  `batch_name` varchar(64) NOT NULL COMMENT '验证码批次名称',
  `type` tinyint(1) unsigned NOT NULL COMMENT '验证码类别：1表示免费票，2表示兑换券，3表示礼品券',
  `create_time` int(11) unsigned NOT NULL COMMENT '验证码生成时间',
  `begin_time` int(11) unsigned NOT NULL COMMENT '验证码有效期开始时间',
  `end_time` int(11) unsigned NOT NULL COMMENT '验证码有效期结束时间',
  `status` tinyint(1) unsigned DEFAULT '0' COMMENT '验证码使用状态：0表示未使用，1表示已使用',
  `user_name` varchar(64) DEFAULT '0' COMMENT '用户名',
  `mobile` int(13) DEFAULT '0' COMMENT '用户手机号',
  `is_on` tinyint(1) unsigned DEFAULT '1' COMMENT '验证码是否可用：1表示可用，0表示被禁用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Snoopy展 验证码表';

ALTER TABLE `wft`.`activity_snoopy_verify_code`
CHANGE COLUMN `type` `type` TINYINT(3) UNSIGNED NOT NULL COMMENT '验证码类别：1表示免费票，2表示兑换券，3表示礼品券......' ;

ALTER TABLE `wft`.`activity_snoopy_verify_code`
CHANGE COLUMN `mobile` `mobile` VARCHAR(15) NULL DEFAULT '0' COMMENT '用户手机号' ;


