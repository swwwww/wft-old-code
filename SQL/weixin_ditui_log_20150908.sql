CREATE TABLE `weixin_ditui_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '数据id，自增',
  `open_id` varchar(200) NOT NULL COMMENT '用户加密的微信号信息',
  `scene` varchar(100) DEFAULT '0' COMMENT '渠道(或场景)',
  `weixin_name` varchar(100) NOT NULL COMMENT '微信名，目前只用于玩翻天微信，以后可能用于公司其他微信公众号',
  `concern_num` int(10) DEFAULT '0' COMMENT '关注次数,超过1次表明关注后曾取消过关注',
  `union_id` varchar(200) DEFAULT '0' COMMENT '多平台下用户唯一标识',
  `nick_name` varchar(200) DEFAULT '0' COMMENT '用户的微信昵称',
  `is_on` tinyint(1) DEFAULT '0' COMMENT '用户当前是否关注,1表示关注,0表示未关注',
  `concern_time` int(11) DEFAULT '0' COMMENT '用户最后一次关注时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='微信地推数据表';
