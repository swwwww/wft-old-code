CREATE TABLE `activity_babygogogo_batch` (
  `batch_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '场次编号',
  `batch_name` varchar(255) NOT NULL COMMENT '场次名称',
  `act_date` int(11) NOT NULL COMMENT '活动开展日期（精确到年月日）',
  `act_time` varchar(255) NOT NULL COMMENT '活动具体时间（由开始时间_结束时间组成的字符串）',
  `address` varchar(255) NOT NULL COMMENT '活动地址',
  `allow_num` int(10) NOT NULL DEFAULT '0' COMMENT '活动接纳人数',
  `register_num` int(10) DEFAULT '0' COMMENT '已报名人数,不能超过接纳人数',
  `join_num` int(10) DEFAULT '0' COMMENT '已参加人数',
  PRIMARY KEY (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='宝贝gogogo场次表';



CREATE TABLE `activity_babygogogo_userinfo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户报名id',
  `uid` varchar(45) NOT NULL COMMENT '用户在玩翻天的账号uid',
  `user_name` varchar(255) NOT NULL COMMENT '报名用户名，一般为家长用户名',
  `register_time` int(11) DEFAULT '0' COMMENT '报名时间',
  `baby_name` varchar(255) NOT NULL COMMENT '宝宝姓名',
  `baby_sex` int(1) NOT NULL COMMENT '宝宝性别,1表示男,0表示女',
  `attend_way` int(1) NOT NULL COMMENT '组团方式,1表示自由组团,2表示现场随机分组',
  `attend_time` int(11) DEFAULT '0' COMMENT '参加时间',
  `batch_id` int(11) NOT NULL DEFAULT '0' COMMENT '参与场次id，与场次表对应',
  `baby_identity_id` varchar(45) DEFAULT '0' COMMENT '宝宝身份证号',
  `user_identity_id` varchar(45) DEFAULT '0' COMMENT '家长身份证号',
  `mobile` varchar(45) DEFAULT '0' COMMENT '家长手机号',
  `is_insure` int(1) DEFAULT '0' COMMENT '是否购买保险，1表示已购买，0表示未购买',
  `attend_status` int(1) DEFAULT '0' COMMENT '参加状态,1表示已参加,0表示未参加',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='宝贝gogogo活动参与用户的信息';


