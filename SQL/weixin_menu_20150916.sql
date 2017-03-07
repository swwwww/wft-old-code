CREATE TABLE `weixin_menu` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '菜单id',
  `button` tinyint(1) unsigned NOT NULL COMMENT '菜单级别标识,1表示是1级菜单,2表示2级菜单，只支持1和2，最多只有2级菜单',
  `type` varchar(50) NOT NULL DEFAULT 'view' COMMENT '菜单的响应动作类型，具体可参考微信开发者文档',
  `menu_name` varchar(100) NOT NULL COMMENT '菜单标题，不超过16个字节(5个汉字)，子菜单不超过40个字节(10个汉字)',
  `key` varchar(255) DEFAULT NULL COMMENT '菜单KEY值，用于消息接口推送，不超过128字节，click等点击类型必须',
  `url` varchar(500) DEFAULT NULL COMMENT '网页链接，用户点击菜单可打开链接，不超过256字节，view类型必须',
  `media_id` varchar(255) DEFAULT NULL COMMENT '调用新增永久素材接口返回的合法media_id，media_id类型和view_limited类型必须',
  `pmid` int(11) NOT NULL DEFAULT '0' COMMENT '父级菜单id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='玩翻天微信自定义菜单表';
