/*
Navicat MySQL Data Transfer

Source Server         : 10.0.18.19
Source Server Version : 50620
Source Host           : 10.0.18.19:3306
Source Database       : learnplay

Target Server Type    : MYSQL
Target Server Version : 50620
File Encoding         : 65001

Date: 2015-03-30 16:55:35
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for play_activity
-- ----------------------------
DROP TABLE IF EXISTS `play_activity`;
CREATE TABLE `play_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '活动id',
  `ac_name` varchar(255) NOT NULL COMMENT '活动名称',
  `ac_cover` varchar(255) NOT NULL COMMENT '图片',
  `coupon_id` varchar(255) DEFAULT NULL COMMENT 'å…³è”çš„å¡åˆ¸id',
  `status` int(11) NOT NULL DEFAULT '0' COMMENT '活动状态 ',
  `introduce` varchar(2000) NOT NULL DEFAULT '' COMMENT 'ä»‹ç»  å°ç¼–è¯´',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '��ǩ',
  `s_time` int(11) NOT NULL DEFAULT '0' COMMENT '��ʼ ʱ�� 0�Ļ�������Ч',
  `e_time` int(11) NOT NULL DEFAULT '0' COMMENT '����ʱ��',
  `uid` int(11) NOT NULL DEFAULT '1210' COMMENT 'С���uid',
  `ac_type` int(11) NOT NULL DEFAULT '3' COMMENT 'ר�����',
  `ac_city` varchar(10) NOT NULL DEFAULT 'WH' COMMENT '����',
  `ac_sort` int(11) NOT NULL DEFAULT '3' COMMENT 'ר�� ����',
  `like_number` int(11) NOT NULL DEFAULT '0' COMMENT '������',
  `count_number` int(11) NOT NULL DEFAULT '0' COMMENT '��ȯ����',
  `dateline` int(11) NOT NULL DEFAULT '0' COMMENT '���ʱ��',
  `allow_post` int(2) DEFAULT '1' COMMENT '是否允许评论  1允许 0不允许',
  `post_number` int(11) DEFAULT '0' COMMENT '评论数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_activity_coupon
-- ----------------------------
DROP TABLE IF EXISTS `play_activity_coupon`;
CREATE TABLE `play_activity_coupon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '������ר�� id',
  `cid` int(11) NOT NULL COMMENT '�����Ŀ�ȯid',
  `act_type` int(3) NOT NULL DEFAULT '3' COMMENT '专题的类别 3默认为一般专题 1一元手慢无 2 周末去那',
  `ac_sort` int(3) NOT NULL DEFAULT '2' COMMENT '����������',
  `type` varchar(10) NOT NULL DEFAULT 'coupon' COMMENT '关联 是卡券 coupon 资讯 news ',
  PRIMARY KEY (`id`),
  KEY `ac_sort` (`ac_sort`) USING BTREE,
  KEY `cid` (`cid`,`type`),
  KEY `act_type` (`act_type`),
  KEY `ac_sort_2` (`ac_sort`)
) ENGINE=InnoDB AUTO_INCREMENT=383 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_activity_tag
-- ----------------------------
DROP TABLE IF EXISTS `play_activity_tag`;
CREATE TABLE `play_activity_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tagname` varchar(255) NOT NULL COMMENT '��ǩ����',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_admin
-- ----------------------------
DROP TABLE IF EXISTS `play_admin`;
CREATE TABLE `play_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_name` varchar(100) NOT NULL COMMENT '管理员 用户名',
  `password` varchar(35) NOT NULL DEFAULT '123456' COMMENT '用户密码',
  `shop_id` int(5) NOT NULL COMMENT '商户id',
  `group` int(1) NOT NULL DEFAULT '0' COMMENT '�����û��飬1Ϊϵͳ����Ա 2 �ǵ��̹���Ա 3Ϊ�༭',
  `dateline` int(11) NOT NULL COMMENT '添加时间',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '未定义',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '������ͷ��',
  `bind_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`,`group`,`password`,`admin_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1364 DEFAULT CHARSET=utf8 COMMENT='后台用户列表';

-- ----------------------------
-- Table structure for play_attach
-- ----------------------------
DROP TABLE IF EXISTS `play_attach`;
CREATE TABLE `play_attach` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL COMMENT '用户id',
  `use_id` int(11) NOT NULL COMMENT '使用id',
  `use_type` varchar(20) NOT NULL COMMENT '使用类型',
  `dateline` int(11) NOT NULL COMMENT '上传时间',
  `url` varchar(255) NOT NULL COMMENT '附件地址',
  `is_remote` int(1) NOT NULL COMMENT '是否远程地址',
  `name` varchar(255) DEFAULT NULL COMMENT '附件真实名称',
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `use_id` (`use_id`,`use_type`),
  KEY `user` (`uid`),
  KEY `user_2` (`uid`,`use_id`)
) ENGINE=InnoDB AUTO_INCREMENT=288 DEFAULT CHARSET=utf8 COMMENT='附件';

-- ----------------------------
-- Table structure for play_auth_code
-- ----------------------------
DROP TABLE IF EXISTS `play_auth_code`;
CREATE TABLE `play_auth_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `time` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3416 DEFAULT CHARSET=utf8 COMMENT='短信验证';

-- ----------------------------
-- Table structure for play_coupons
-- ----------------------------
DROP TABLE IF EXISTS `play_coupons`;
CREATE TABLE `play_coupons` (
  `coupon_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `coupon_name` varchar(255) NOT NULL COMMENT '卡券名称',
  `coupon_typename` varchar(30) NOT NULL COMMENT '卡券类别 type_name',
  `coupon_marketname` varchar(255) NOT NULL COMMENT '商户名称',
  `coupon_marketid` int(11) NOT NULL COMMENT '商户id',
  `coupon_shopid` varchar(255) NOT NULL COMMENT '店铺id json',
  `coupon_originprice` decimal(9,2) NOT NULL COMMENT '原价',
  `coupon_price` decimal(9,2) NOT NULL COMMENT '现价',
  `coupon_total` int(11) NOT NULL COMMENT '卡券总数',
  `coupon_share` int(2) NOT NULL COMMENT '分享限制 1不限制 2限制',
  `coupon_appointment` int(2) NOT NULL DEFAULT '1' COMMENT '是否预约 1无需预约  2 需预约',
  `coupon_limitnum` int(11) NOT NULL DEFAULT '0' COMMENT '每机限购数量',
  `coupon_introduce` text COMMENT '用户须知',
  `coupon_description` text NOT NULL COMMENT '图文说明',
  `coupon_close` int(11) NOT NULL COMMENT '卡券使用截止时间',
  `coupon_uptime` int(11) NOT NULL COMMENT '上架时间',
  `coupon_starttime` int(11) NOT NULL COMMENT '开始时间',
  `coupon_endtime` int(11) NOT NULL COMMENT '结束时间',
  `coupon_cover` varchar(255) NOT NULL COMMENT '封面图',
  `coupon_dateline` int(11) NOT NULL COMMENT '卡券 添加时间',
  `coupon_use` int(11) NOT NULL DEFAULT '0' COMMENT '0é¦–é¡µ  2æ´»åŠ¨',
  `coupon_status` int(2) NOT NULL DEFAULT '1' COMMENT '卡券发布状态 1发布 0 取消发布',
  `coupon_buy` int(11) NOT NULL DEFAULT '0' COMMENT '已售',
  `coupon_vir` int(11) NOT NULL DEFAULT '0' COMMENT '虚拟票',
  `coupon_city` varchar(10) NOT NULL DEFAULT 'WH' COMMENT '��ȯ����',
  `editor_id` int(11) NOT NULL DEFAULT '0' COMMENT '�༭�ÿ�ȯ��С��id',
  `editor_word` varchar(255) NOT NULL DEFAULT '' COMMENT 'С��˵',
  `use_time` varchar(255) NOT NULL DEFAULT '' COMMENT 'ʹ��ʱ��',
  `attend_method` varchar(255) NOT NULL DEFAULT '' COMMENT '�μӷ���',
  `matters_attention` varchar(255) NOT NULL DEFAULT '' COMMENT 'ע������',
  `use_info` varchar(255) NOT NULL DEFAULT '' COMMENT 'ʹ��˵��',
  `age_min` int(11) NOT NULL DEFAULT '0' COMMENT '�������� С',
  `age_max` int(11) NOT NULL DEFAULT '6' COMMENT '�������� ��',
  `new_user` int(2) NOT NULL DEFAULT '0' COMMENT '���û�ר�� 1 Ϊ���û�ר��',
  `allow_post` int(2) DEFAULT '1' COMMENT '是否允许评论  1允许 0不允许',
  `post_number` int(11) NOT NULL DEFAULT '0' COMMENT '评论数',
  `coupon_thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '缩略图',
  `coupon_join` int(2) NOT NULL DEFAULT '1' COMMENT '是否合作商品  1合作 0 非合作',
  `coupon_remind` varchar(400) DEFAULT NULL COMMENT '购买提醒',
  `coupon_click` int(11) DEFAULT '0',
  PRIMARY KEY (`coupon_id`),
  KEY `appointment` (`coupon_appointment`),
  KEY `coupon_name` (`coupon_name`) USING BTREE,
  KEY `coupon_price` (`coupon_price`) USING BTREE,
  KEY `coupon_marketid` (`coupon_marketid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=290 DEFAULT CHARSET=utf8 COMMENT='商品';

-- ----------------------------
-- Table structure for play_coupons_linker
-- ----------------------------
DROP TABLE IF EXISTS `play_coupons_linker`;
CREATE TABLE `play_coupons_linker` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `coupon_id` int(11) NOT NULL COMMENT '关联的卡券id',
  `shop_id` int(11) NOT NULL COMMENT '关联的店铺id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3072 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_coupon_code
-- ----------------------------
DROP TABLE IF EXISTS `play_coupon_code`;
CREATE TABLE `play_coupon_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_sn` int(9) unsigned zerofill NOT NULL COMMENT '订单',
  `sort` int(11) NOT NULL COMMENT '第几张卡券',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '使用状态,0未使用,1已使用,2已退款,3退款中',
  `password` varchar(20) NOT NULL,
  `use_store` int(11) NOT NULL COMMENT '使用店铺',
  `use_datetime` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_sn` (`order_sn`) USING BTREE,
  KEY `status` (`status`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=5187 DEFAULT CHARSET=utf8 COMMENT='订单密码关联表';

-- ----------------------------
-- Table structure for play_coupon_type
-- ----------------------------
DROP TABLE IF EXISTS `play_coupon_type`;
CREATE TABLE `play_coupon_type` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL COMMENT '该分类的父id',
  `type_name` varchar(90) NOT NULL COMMENT '分类名称',
  `acr` varchar(30) NOT NULL DEFAULT 'WH' COMMENT '城市前缀',
  `city` varchar(40) NOT NULL COMMENT '城市',
  PRIMARY KEY (`type_id`),
  KEY `coupon_type` (`type_id`,`parent_id`,`type_name`,`acr`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='商品分类';

-- ----------------------------
-- Table structure for play_feedback
-- ----------------------------
DROP TABLE IF EXISTS `play_feedback`;
CREATE TABLE `play_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL COMMENT '用户id',
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `dateline` int(11) DEFAULT NULL,
  `api_error_info` int(1) DEFAULT '0' COMMENT '1.反馈接口错误信息',
  `contact` varchar(50) DEFAULT '联系方式',
  `is_ok` int(1) NOT NULL DEFAULT '0' COMMENT '是否解决',
  PRIMARY KEY (`id`),
  KEY `error_info` (`api_error_info`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='意见反馈';

-- ----------------------------
-- Table structure for play_index_block
-- ----------------------------
DROP TABLE IF EXISTS `play_index_block`;
CREATE TABLE `play_index_block` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL COMMENT '关联id 可以是卡券 专题 资讯 游玩地',
  `type` int(2) NOT NULL DEFAULT '1' COMMENT '类别 1 专题 2 卡券 3 资讯 4 游玩地',
  `block_order` int(4) NOT NULL DEFAULT '1' COMMENT '排序',
  `dateline` int(11) NOT NULL COMMENT '添加 修改时间',
  `block_city` varchar(10) NOT NULL DEFAULT 'WH' COMMENT '城市 首页',
  `editor_image` varchar(255) NOT NULL DEFAULT '' COMMENT '小编头像',
  `coupon_have` int(2) NOT NULL DEFAULT '1' COMMENT '是否有票 1有 0没',
  `price` decimal(10,2) DEFAULT NULL COMMENT '价格',
  `link_type` int(2) NOT NULL DEFAULT '1' COMMENT '关联类型 1列表  2焦点图',
  `tip` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`),
  KEY `blcok` (`link_id`,`type`,`block_order`,`dateline`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_like
-- ----------------------------
DROP TABLE IF EXISTS `play_like`;
CREATE TABLE `play_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `like_id` int(11) NOT NULL,
  `type` varchar(10) DEFAULT 'activity' COMMENT '��������',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`,`like_id`,`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=408 DEFAULT CHARSET=utf8 COMMENT='ç‚¹èµžè¡¨';

-- ----------------------------
-- Table structure for play_mabaobao
-- ----------------------------
DROP TABLE IF EXISTS `play_mabaobao`;
CREATE TABLE `play_mabaobao` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `sex` varchar(20) NOT NULL DEFAULT 'boy' COMMENT '男 女',
  `birthday` int(13) NOT NULL COMMENT '宝贝生日',
  `address` varchar(50) NOT NULL COMMENT '出生地',
  `username` varchar(255) NOT NULL COMMENT '宝宝名称',
  `check_status` int(3) NOT NULL DEFAULT '0' COMMENT '0 未审核， 1通过   -1 未通过',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_market
-- ----------------------------
DROP TABLE IF EXISTS `play_market`;
CREATE TABLE `play_market` (
  `market_id` int(5) unsigned zerofill NOT NULL AUTO_INCREMENT COMMENT '商户id',
  `market_name` varchar(255) NOT NULL COMMENT '商家名称',
  `market_type` varchar(60) NOT NULL DEFAULT '0' COMMENT '商家类型',
  `market_city` varchar(60) NOT NULL DEFAULT 'WH' COMMENT '�̼����ڳ���',
  `market_status` int(11) NOT NULL DEFAULT '0' COMMENT '商家状态 0 正常 -1 关闭',
  PRIMARY KEY (`market_id`),
  KEY `market_type` (`market_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=143 DEFAULT CHARSET=utf8 COMMENT='商家';

-- ----------------------------
-- Table structure for play_news
-- ----------------------------
DROP TABLE IF EXISTS `play_news`;
CREATE TABLE `play_news` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '资讯id',
  `title` varchar(255) NOT NULL COMMENT '资讯标题',
  `editor_word` varchar(2000) NOT NULL,
  `information` text NOT NULL COMMENT '图文详情',
  `age_max` int(11) NOT NULL DEFAULT '6' COMMENT '年龄区间 大',
  `age_min` int(11) NOT NULL DEFAULT '0' COMMENT '年龄区间 小',
  `reference_price` decimal(10,2) NOT NULL COMMENT '参考价格',
  `address` varchar(1800) NOT NULL COMMENT '地址',
  `cover` varchar(255) NOT NULL COMMENT '封面图',
  `surface_plot` varchar(255) NOT NULL COMMENT '封面 正方形',
  `dateline` int(11) NOT NULL COMMENT '添加 修改 时间',
  `view_nums` int(11) NOT NULL DEFAULT '1' COMMENT '查看数',
  `post_number` int(11) NOT NULL DEFAULT '0' COMMENT '回复数',
  `status` int(11) NOT NULL DEFAULT '1' COMMENT '状态 1 发布 0 没发布 -1 删除',
  `editor_id` int(11) NOT NULL COMMENT '小编id',
  `news_city` varchar(10) NOT NULL COMMENT '城市',
  `allow_post` int(2) DEFAULT '1' COMMENT '是否允许评论  1允许  0不允许',
  PRIMARY KEY (`id`),
  KEY `statu` (`dateline`,`status`) USING BTREE,
  KEY `age` (`age_max`,`age_min`) USING BTREE,
  KEY `price` (`reference_price`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_order_action
-- ----------------------------
DROP TABLE IF EXISTS `play_order_action`;
CREATE TABLE `play_order_action` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单号',
  `play_status` int(11) DEFAULT NULL COMMENT '付款状态  0未付款 1付款中 2已付款 3退款中 4退款成功  5已使用',
  `action_user` int(11) DEFAULT NULL COMMENT '操作员',
  `action_note` text COMMENT 'æ“ä½œå¤‡æ³¨',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`action_id`),
  KEY `order_id` (`order_id`),
  KEY `play_status` (`play_status`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB AUTO_INCREMENT=9324 DEFAULT CHARSET=utf8 COMMENT='订单操作表';

-- ----------------------------
-- Table structure for play_order_info
-- ----------------------------
DROP TABLE IF EXISTS `play_order_info`;
CREATE TABLE `play_order_info` (
  `order_sn` int(9) unsigned zerofill NOT NULL AUTO_INCREMENT COMMENT '唯一订单号',
  `coupon_id` int(11) DEFAULT NULL COMMENT '卡券id',
  `order_status` int(11) DEFAULT '1' COMMENT '订单状态,1为正常,0 删除',
  `pay_status` tinyint(4) DEFAULT '0' COMMENT '付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用',
  `user_id` int(11) DEFAULT NULL COMMENT '用户id',
  `username` varchar(255) DEFAULT NULL COMMENT '用户名称',
  `real_pay` decimal(8,2) DEFAULT NULL COMMENT '真实付款',
  `voucher` decimal(8,2) DEFAULT NULL COMMENT '代金券付款金额',
  `voucher_id` int(11) DEFAULT NULL COMMENT '代金券 id',
  `coupon_unit_price` decimal(8,2) DEFAULT NULL COMMENT '卡券单价',
  `coupon_name` varchar(255) DEFAULT NULL COMMENT '卡券名称',
  `shop_name` varchar(255) DEFAULT NULL COMMENT '商家名称',
  `shop_id` varchar(255) DEFAULT NULL COMMENT '商家id',
  `buy_number` int(11) DEFAULT NULL COMMENT '订单包含的卡券数',
  `use_number` int(11) DEFAULT '0' COMMENT '使用数量',
  `back_number` int(11) DEFAULT '0' COMMENT '退订数',
  `account` varchar(255) DEFAULT NULL COMMENT '支付账号',
  `account_type` enum('weixin','alipay') DEFAULT NULL COMMENT '账号类型',
  `buy_name` varchar(40) DEFAULT NULL COMMENT '购买姓名',
  `buy_phone` varchar(20) DEFAULT NULL COMMENT '购买电话',
  `dateline` int(11) DEFAULT NULL COMMENT '下单时间',
  `use_dateline` int(11) DEFAULT NULL COMMENT '最后使用时间',
  `trade_no` varchar(50) DEFAULT NULL,
  `order_city` varchar(10) DEFAULT 'WH',
  PRIMARY KEY (`order_sn`),
  KEY `pay_status` (`pay_status`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `shop_id` (`shop_id`) USING BTREE,
  KEY `dateline` (`dateline`) USING BTREE,
  KEY `voucher` (`voucher`)
) ENGINE=InnoDB AUTO_INCREMENT=24063 DEFAULT CHARSET=utf8 COMMENT='订单信息';

-- ----------------------------
-- Table structure for play_post
-- ----------------------------
DROP TABLE IF EXISTS `play_post`;
CREATE TABLE `play_post` (
  `pid` int(11) NOT NULL AUTO_INCREMENT COMMENT '回复id',
  `type` char(10) NOT NULL DEFAULT 'activity' COMMENT '评论类型 activity  or coupon or news',
  `object_id` int(11) NOT NULL COMMENT '评论对象 id',
  `first` int(11) NOT NULL DEFAULT '0' COMMENT '是否主题帖',
  `uid` int(11) NOT NULL COMMENT '用户id',
  `author` varchar(20) NOT NULL COMMENT '用户名',
  `subject` varchar(50) DEFAULT NULL COMMENT '标题',
  `dateline` int(11) NOT NULL,
  `message` text NOT NULL COMMENT '帖子内容',
  `userip` varchar(15) NOT NULL COMMENT 'ip地址',
  `displayorder` int(11) NOT NULL COMMENT '0已删除，1正常 2置顶',
  `replypid` int(11) DEFAULT NULL COMMENT '回复楼层id? post id',
  `img` varchar(255) DEFAULT NULL COMMENT '用户头像',
  `photo_number` int(11) NOT NULL DEFAULT '0',
  `photo_list` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`pid`),
  KEY `replypid` (`replypid`),
  KEY `displayorder` (`displayorder`) USING BTREE,
  KEY `type` (`type`) USING BTREE,
  KEY `object_id` (`object_id`,`first`,`uid`,`subject`,`dateline`) USING BTREE,
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB AUTO_INCREMENT=436 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_prize
-- ----------------------------
DROP TABLE IF EXISTS `play_prize`;
CREATE TABLE `play_prize` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `prize_id` int(11) NOT NULL COMMENT '奖品id',
  `prize_type` varchar(10) DEFAULT NULL COMMENT '奖品类型',
  `activity_type` varchar(10) NOT NULL COMMENT '活动类型',
  `dateline` int(11) NOT NULL COMMENT '操作时间',
  `phone` varchar(11) DEFAULT NULL,
  `share_uid` int(11) DEFAULT NULL COMMENT '由谁分享出来的',
  `status` int(11) DEFAULT '1' COMMENT '状态 0已回收 1等待确认  2已确认',
  `notice_id` int(11) DEFAULT NULL COMMENT '提示语id',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `activity_type` (`activity_type`),
  KEY `dateline` (`dateline`),
  KEY `phone` (`phone`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=338 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_prize_log
-- ----------------------------
DROP TABLE IF EXISTS `play_prize_log`;
CREATE TABLE `play_prize_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `action` int(10) NOT NULL COMMENT '操作 1.抽奖 2.兑奖 3.获得机会数',
  `result` varchar(255) DEFAULT NULL,
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dateline` (`dateline`),
  KEY `action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=534 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_prize_userdata
-- ----------------------------
DROP TABLE IF EXISTS `play_prize_userdata`;
CREATE TABLE `play_prize_userdata` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `activity_type` varchar(10) NOT NULL COMMENT '活动类型',
  `chance_number` int(11) NOT NULL DEFAULT '0' COMMENT '抽奖机会数',
  `expiry_number` int(11) NOT NULL DEFAULT '0' COMMENT '兑奖数',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`,`activity_type`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_region
-- ----------------------------
DROP TABLE IF EXISTS `play_region`;
CREATE TABLE `play_region` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rid` varchar(20) NOT NULL COMMENT '地区编码',
  `name` varchar(20) NOT NULL COMMENT '商圈名称',
  `acr` varchar(10) DEFAULT 'WH',
  PRIMARY KEY (`id`),
  KEY `rid` (`rid`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8 COMMENT='商圈';

-- ----------------------------
-- Table structure for play_region_linker
-- ----------------------------
DROP TABLE IF EXISTS `play_region_linker`;
CREATE TABLE `play_region_linker` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL COMMENT '关联id 可以是资讯 也可以是卡券',
  `region_id` int(11) NOT NULL COMMENT 'region的 rid',
  `type` int(2) NOT NULL DEFAULT '1' COMMENT '类型 1为卡券 2 为 资讯',
  PRIMARY KEY (`id`),
  KEY `type_link_region` (`link_id`,`region_id`,`type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_search_form_value
-- ----------------------------
DROP TABLE IF EXISTS `play_search_form_value`;
CREATE TABLE `play_search_form_value` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `val` varchar(255) DEFAULT NULL,
  `dateline` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT '0' COMMENT '状态  0已过期 1正常',
  PRIMARY KEY (`id`),
  KEY `dateline` (`dateline`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COMMENT='搜索框默认关键字';

-- ----------------------------
-- Table structure for play_search_log
-- ----------------------------
DROP TABLE IF EXISTS `play_search_log`;
CREATE TABLE `play_search_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `key` varchar(255) DEFAULT NULL,
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=848 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_settings
-- ----------------------------
DROP TABLE IF EXISTS `play_settings`;
CREATE TABLE `play_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `set_name` varchar(255) NOT NULL COMMENT '设置名称',
  `set_value` varchar(255) DEFAULT NULL COMMENT '设置值',
  `dateline` int(11) DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `set_name` (`set_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for play_share
-- ----------------------------
DROP TABLE IF EXISTS `play_share`;
CREATE TABLE `play_share` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `type` char(10) NOT NULL COMMENT '分享类型 coupon 卡券',
  `share_id` int(11) NOT NULL COMMENT '分享对象id',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`,`type`,`share_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6600 DEFAULT CHARSET=utf8 COMMENT='分享记录表';

-- ----------------------------
-- Table structure for play_shop
-- ----------------------------
DROP TABLE IF EXISTS `play_shop`;
CREATE TABLE `play_shop` (
  `shop_id` int(6) NOT NULL AUTO_INCREMENT COMMENT '店铺id',
  `shop_mid` int(5) unsigned zerofill NOT NULL COMMENT 'shop  父id  market_id',
  `shop_type` int(11) NOT NULL DEFAULT '1' COMMENT '店铺类型  1 合作  2非合作',
  `shop_city` varchar(30) NOT NULL COMMENT '店铺所在城市',
  `shop_address` varchar(255) NOT NULL COMMENT '地址',
  `addr_x` varchar(255) NOT NULL COMMENT '经度坐标',
  `addr_y` varchar(255) NOT NULL COMMENT '纬度坐标',
  `shop_phone` varchar(255) NOT NULL COMMENT '电话',
  `busniess_circle` varchar(255) NOT NULL COMMENT '商圈',
  `shop_name` varchar(255) NOT NULL COMMENT '店铺名称',
  `shop_open` int(11) NOT NULL COMMENT '营业时间 开始',
  `shop_close` int(11) NOT NULL COMMENT '营业时间 结束',
  `shop_status` int(11) NOT NULL DEFAULT '0' COMMENT '店铺状态 0正常状态 -1关闭',
  `password` varchar(255) NOT NULL DEFAULT '123456' COMMENT '����',
  `post_number` int(11) NOT NULL DEFAULT '0' COMMENT '评论总数',
  `allow_post` int(2) NOT NULL DEFAULT '1' COMMENT '是否允许评论  1允许  0不允许',
  `dateline` int(11) NOT NULL COMMENT '添加 修改时间',
  `editor_id` int(11) NOT NULL DEFAULT '1330' COMMENT '编辑id',
  `cover` varchar(255) NOT NULL COMMENT '封面图',
  `editor_word` varchar(2000) NOT NULL COMMENT '小玩说',
  `information` text NOT NULL COMMENT '图文详情',
  `age_min` int(11) NOT NULL DEFAULT '0' COMMENT '年龄区间 小',
  `age_max` int(11) NOT NULL DEFAULT '6' COMMENT '年龄区间 大',
  `reference_price` decimal(10,2) NOT NULL COMMENT '参考价格',
  `hot_count` int(11) DEFAULT NULL,
  `shop_click` int(11) DEFAULT '0',
  `thumbnails` varchar(255) NOT NULL COMMENT '缩率图',
  PRIMARY KEY (`shop_id`),
  KEY `addr_x` (`addr_x`) USING BTREE,
  KEY `addr_y` (`addr_y`) USING BTREE,
  KEY `addr_xy` (`addr_x`,`addr_y`) USING BTREE,
  KEY `acr_city` (`shop_city`) USING BTREE,
  KEY `shop_name` (`shop_name`) USING BTREE,
  KEY `shop_status` (`shop_status`) USING BTREE,
  KEY `shop_type` (`shop_type`) USING BTREE,
  KEY `hot_count` (`hot_count`)
) ENGINE=InnoDB AUTO_INCREMENT=141 DEFAULT CHARSET=utf8 COMMENT='店铺表';

-- ----------------------------
-- Table structure for play_user
-- ----------------------------
DROP TABLE IF EXISTS `play_user`;
CREATE TABLE `play_user` (
  `uid` int(11) NOT NULL AUTO_INCREMENT COMMENT '用户id',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `password` varchar(50) DEFAULT NULL COMMENT '登陆密码',
  `child_sex` int(11) DEFAULT '0' COMMENT '子女性别0 未知  1 男 2 女',
  `child_old` int(11) DEFAULT NULL COMMENT '孩子年龄',
  `token` varchar(255) NOT NULL COMMENT '带有有效期的第三方token',
  `mark_info` varchar(100) NOT NULL COMMENT '第三方唯一标识',
  `phone` varchar(15) DEFAULT NULL COMMENT '绑定 手机号',
  `login_type` varchar(10) NOT NULL COMMENT '注册类型 phone  qq weixing weibo deyi',
  `is_online` int(1) NOT NULL COMMENT '用户是否在线 0 否 1 在',
  `device_type` enum('android','ios') NOT NULL COMMENT '设备类型,可用户推送服务',
  `push_id` varchar(100) DEFAULT NULL COMMENT '推送 id',
  `dateline` int(11) NOT NULL COMMENT '注册时间',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '用户状态，1为开启',
  `img` varchar(255) DEFAULT NULL COMMENT '用户头像',
  `city` varchar(10) NOT NULL DEFAULT 'WH' COMMENT '�û����ڳ���',
  PRIMARY KEY (`uid`),
  KEY `mark_info` (`mark_info`,`username`,`login_type`,`is_online`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=13097 DEFAULT CHARSET=utf8 COMMENT='用户主表';

-- ----------------------------
-- Table structure for play_user_linker
-- ----------------------------
DROP TABLE IF EXISTS `play_user_linker`;
CREATE TABLE `play_user_linker` (
  `linker_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单联系人 id',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `linker_name` varchar(50) NOT NULL COMMENT '联系人姓名',
  `linker_phone` varchar(30) NOT NULL COMMENT '联系人 电话',
  PRIMARY KEY (`linker_id`)
) ENGINE=InnoDB AUTO_INCREMENT=204 DEFAULT CHARSET=utf8 COMMENT='用户手机号列表';
