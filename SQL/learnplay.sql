/*
Navicat MySQL Data Transfer

Source Server         : 10.0.18.19
Source Server Version : 50620
Source Host           : 10.0.18.19:3306
Source Database       : learnplay

Target Server Type    : MYSQL
Target Server Version : 50620
File Encoding         : 65001

Date: 2014-11-25 09:41:57
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `play_admin`
-- ----------------------------
DROP TABLE IF EXISTS `play_admin`;
CREATE TABLE `play_admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_name` varchar(100) NOT NULL COMMENT '管理员 用户名',
  `password` varchar(35) NOT NULL DEFAULT '123456' COMMENT '用户密码',
  `shop_id` int(5) NOT NULL COMMENT '商户id',
  `group` int(1) NOT NULL DEFAULT '0' COMMENT '所属用户组，1为系统管理员 2 是店铺管理员',
  `dateline` int(11) NOT NULL COMMENT '添加时间',
  `status` int(1) NOT NULL DEFAULT '0' COMMENT '未定义',
  PRIMARY KEY (`id`),
  KEY `shop_id` (`shop_id`,`group`,`password`,`admin_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1225 DEFAULT CHARSET=utf8 COMMENT='后台用户列表';

-- ----------------------------
-- Records of play_admin
-- ----------------------------
INSERT INTO `play_admin` VALUES ('1210', 'deyilife', 'e10adc3949ba59abbe56e057f20f883e', '0', '1', '0', '1');
INSERT INTO `play_admin` VALUES ('1211', 'shop', 'e10adc3949ba59abbe56e057f20f883e', '1', '2', '0', '1');
INSERT INTO `play_admin` VALUES ('1212', '贝乐园（汉阳摩尔城店）【测试】', 'e10adc3949ba59abbe56e057f20f883e', '18', '2', '1416814137', '1');
INSERT INTO `play_admin` VALUES ('1213', '贝乐园', 'e10adc3949ba59abbe56e057f20f883e', '17', '2', '1416814190', '1');
INSERT INTO `play_admin` VALUES ('1214', '贝乐园（武广）【测试】', 'e10adc3949ba59abbe56e057f20f883e', '16', '2', '1416814213', '1');
INSERT INTO `play_admin` VALUES ('1215', '贝乐园（武广）【测试】', 'e10adc3949ba59abbe56e057f20f883e', '16', '2', '1416814246', '1');
INSERT INTO `play_admin` VALUES ('1216', '孩子王童乐园（沌口 万达）【测试】', 'e10adc3949ba59abbe56e057f20f883e', '15', '2', '1416814271', '1');
INSERT INTO `play_admin` VALUES ('1217', '孩子王童乐园（后湖城市广场）【测试】', 'e10adc3949ba59abbe56e057f20f883e', '14', '2', '1416814340', '1');
INSERT INTO `play_admin` VALUES ('1218', '孩子王童乐园奥山世纪城店【测试】', 'e10adc3949ba59abbe56e057f20f883e', '13', '2', '1416814357', '1');
INSERT INTO `play_admin` VALUES ('1219', '奥山冰雪主题公园【测试】', 'e10adc3949ba59abbe56e057f20f883e', '12', '2', '1416814374', '1');
INSERT INTO `play_admin` VALUES ('1220', '武汉宝林果园【测试】', 'e10adc3949ba59abbe56e057f20f883e', '11', '2', '1416814393', '1');
INSERT INTO `play_admin` VALUES ('1221', '我爱泥DIY手工艺术陶吧【测试】', 'e10adc3949ba59abbe56e057f20f883e', '10', '2', '1416814422', '1');
INSERT INTO `play_admin` VALUES ('1222', '武汉云齐亲子营【测试】', 'e10adc3949ba59abbe56e057f20f883e', '9', '2', '1416814451', '1');
INSERT INTO `play_admin` VALUES ('1223', '马可儿童拓展乐园【测试】', 'e10adc3949ba59abbe56e057f20f883e', '8', '2', '1416814483', '1');
INSERT INTO `play_admin` VALUES ('1224', '贝贝兔', 'e10adc3949ba59abbe56e057f20f883e', '7', '2', '1416814496', '1');

-- ----------------------------
-- Table structure for `play_attach`
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
  PRIMARY KEY (`id`),
  KEY `use_id` (`use_id`,`use_type`),
  KEY `user` (`uid`),
  KEY `user_2` (`uid`,`use_id`)
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8 COMMENT='附件';

-- ----------------------------
-- Records of play_attach
-- ----------------------------
INSERT INTO `play_attach` VALUES ('33', '1', '7', 'coupons', '1415936840', '/uploads/2014/11/14/18a9f061b9b8bb81b7394ef05f62d298.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('34', '1', '7', 'coupons', '1415936840', '/uploads/2014/11/14/cfe47cbc5b6d4d3d77cd042b78be5086.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('35', '1', '6', 'coupons', '1415937346', '/uploads/2014/11/14/e587778cc199fa130ef0d660ab288a25.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('36', '1', '6', 'coupons', '1415937346', '/uploads/2014/11/14/1a9b6fd1804cd73c5e0b2526f92d3833.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('37', '1', '5', 'coupons', '1415937352', '/uploads/2014/11/14/8f9586b15a767cd3496634e98dca72ff.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('38', '1', '5', 'coupons', '1415937352', '/uploads/2014/11/14/fd9a54e4c00b19aeae9f58aafc43f023.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('56', '1', '9', 'coupons', '1416364627', '/uploads/2014/11/19/273b14136cb4b2d25822154aa075a615.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('58', '1', '8', 'coupons', '1416554652', '/uploads/2014/11/19/b07af2ed3b8a0e196676b85b878f8663.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('59', '1', '10', 'coupons', '1416554899', '/uploads/2014/11/19/56f2f780edf1c4838aa920b4bbd74610.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('72', '1', '12', 'coupons', '1416806924', '/uploads/2014/11/22/ad4c5cb63b858b4c01bcf36d9c0b5cf5.jpg', '0', null);
INSERT INTO `play_attach` VALUES ('74', '1', '11', 'coupons', '1416818950', '/uploads/2014/11/22/8f93b85660d3210c5d564a90519478bc.jpg', '0', null);

-- ----------------------------
-- Table structure for `play_auth_code`
-- ----------------------------
DROP TABLE IF EXISTS `play_auth_code`;
CREATE TABLE `play_auth_code` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `time` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='短信验证';

-- ----------------------------
-- Records of play_auth_code
-- ----------------------------

-- ----------------------------
-- Table structure for `play_coupons`
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
  `coupon_status` int(2) NOT NULL DEFAULT '1' COMMENT '卡券发布状态 1发布 0 取消发布',
  `coupon_buy` int(11) NOT NULL DEFAULT '0' COMMENT '已售',
  `coupon_vir` int(11) NOT NULL DEFAULT '0' COMMENT '虚拟票',
  PRIMARY KEY (`coupon_id`),
  KEY `appointment` (`coupon_appointment`),
  KEY `coupon_name` (`coupon_name`) USING BTREE,
  KEY `coupon_price` (`coupon_price`) USING BTREE,
  KEY `coupon_marketid` (`coupon_marketid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COMMENT='商品';

-- ----------------------------
-- Records of play_coupons
-- ----------------------------
INSERT INTO `play_coupons` VALUES ('11', '水上乐园', '展览', '贝乐园【测试】', '30', '[\"16\",\"17\",\"18\"]', '90.00', '0.01', '100', '1', '1', '0', '是否士大夫士大夫', '&lt;p&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401052__1719111.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;/p&gt;&lt;p style=&quot;margin-top: 10px; margin-bottom: 10px; padding: 0px; font-weight: normal; font-stretch: normal; font-size: 14px; line-height: 24px; font-family: &amp;#39;helvetica neue&amp;#39;, helvetica, arial, simsun, 宋体, &amp;#39;Hiragino Sans GB&amp;#39;, sans-serif; color: rgb(102, 102, 102); white-space: normal;&quot;&gt;泡泡堂儿童乐园包含决明子沙池、七彩球池、蹦蹦床、大小滑梯、独木桥、攀岩墙等先进游乐设施，在这里玩乐可以满足宝宝各种娱乐需求，通过这些体验可以使宝贝们养成自信，有主见，与他人友好相处的习惯，在这里，我们还能通过各种亲子互动活动培养幼儿全面发展，分享您宝宝成长道路上的点点滴滴，让您的孩子快乐成长！（美团网摄影师：赵一鸣）&lt;/p&gt;&lt;p&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401008__3906838.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;1057&quot; src=&quot;http://p0.meituan.net/deal/__38401011__8136930.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401018__6608913.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401023__2597784.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;1057&quot; src=&quot;http://p1.meituan.net/deal/__38401027__3972616.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401037__5235124.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401044__4727151.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401048__2116307.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401056__8240159.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401061__1355958.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401066__6047413.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;/p&gt;&lt;p&gt;&lt;br/&gt;&lt;/p&gt;', '1417435200', '1416520800', '1416528000', '1416844740', '/uploads/2014/11/22/8f93b85660d3210c5d564a90519478bc.jpg', '1416818950', '1', '55', '7');
INSERT INTO `play_coupons` VALUES ('12', '玩沙子', '运动', '贝乐园【测试】', '30', '[\"17\",\"18\"]', '99.00', '0.01', '56', '2', '2', '3', null, '&lt;p&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401052__1719111.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;/p&gt;&lt;p id=&quot;yui_3_16_0_1_1416557262351_1530&quot; style=&quot;margin-top: 10px; margin-bottom: 10px; padding: 0px; font-weight: normal; font-stretch: normal; font-size: 14px; line-height: 24px; font-family: &amp;#39;helvetica neue&amp;#39;, helvetica, arial, simsun, 宋体, &amp;#39;Hiragino Sans GB&amp;#39;, sans-serif; color: rgb(102, 102, 102); white-space: normal;&quot;&gt;泡泡堂儿童乐园包含决明子沙池、七彩球池、蹦蹦床、大小滑梯、独木桥、攀岩墙等先进游乐设施，在这里玩乐可以满足宝宝各种娱乐需求，通过这些体验可以使宝贝们养成自信，有主见，与他人友好相处的习惯，在这里，我们还能通过各种亲子互动活动培养幼儿全面发展，分享您宝宝成长道路上的点点滴滴，让您的孩子快乐成长！（美团网摄影师：赵一鸣）&lt;/p&gt;&lt;p&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401008__3906838.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;1057&quot; src=&quot;http://p0.meituan.net/deal/__38401011__8136930.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401018__6608913.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401023__2597784.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;1057&quot; src=&quot;http://p1.meituan.net/deal/__38401027__3972616.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401037__5235124.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401044__4727151.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401048__2116307.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401056__8240159.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p1.meituan.net/deal/__38401061__1355958.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;img height=&quot;466&quot; src=&quot;http://p0.meituan.net/deal/__38401066__6047413.jpg&quot; alt=&quot;美团图&quot; class=&quot;standard-image&quot; style=&quot;display: block; margin: 10px 0px; max-width: 702px; color: rgb(102, 102, 102); font-family: Tahoma, Helvetica, arial, sans-serif; font-size: 14px; font-weight: normal; line-height: 21px; white-space: normal;&quot;/&gt;&lt;/p&gt;&lt;p&gt;&lt;br/&gt;&lt;/p&gt;', '1417435200', '1416520800', '1416700800', '1417103940', '/uploads/2014/11/22/ad4c5cb63b858b4c01bcf36d9c0b5cf5.jpg.thumb.jpg', '1416806924', '1', '0', '5');

-- ----------------------------
-- Table structure for `play_coupons_linker`
-- ----------------------------
DROP TABLE IF EXISTS `play_coupons_linker`;
CREATE TABLE `play_coupons_linker` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `coupon_id` int(11) NOT NULL COMMENT '关联的卡券id',
  `shop_id` int(11) NOT NULL COMMENT '关联的店铺id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of play_coupons_linker
-- ----------------------------
INSERT INTO `play_coupons_linker` VALUES ('1', '7', '4');
INSERT INTO `play_coupons_linker` VALUES ('2', '6', '1');
INSERT INTO `play_coupons_linker` VALUES ('3', '6', '2');
INSERT INTO `play_coupons_linker` VALUES ('4', '5', '1');
INSERT INTO `play_coupons_linker` VALUES ('5', '4', '1');
INSERT INTO `play_coupons_linker` VALUES ('6', '4', '2');
INSERT INTO `play_coupons_linker` VALUES ('7', '4', '3');
INSERT INTO `play_coupons_linker` VALUES ('19', '3', '1');
INSERT INTO `play_coupons_linker` VALUES ('57', '9', '1');
INSERT INTO `play_coupons_linker` VALUES ('59', '8', '1');
INSERT INTO `play_coupons_linker` VALUES ('60', '10', '1');
INSERT INTO `play_coupons_linker` VALUES ('93', '12', '17');
INSERT INTO `play_coupons_linker` VALUES ('94', '12', '18');
INSERT INTO `play_coupons_linker` VALUES ('98', '11', '16');
INSERT INTO `play_coupons_linker` VALUES ('99', '11', '17');
INSERT INTO `play_coupons_linker` VALUES ('100', '11', '18');

-- ----------------------------
-- Table structure for `play_coupon_code`
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
  KEY `order_sn` (`order_sn`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8 COMMENT='订单密码关联表';

-- ----------------------------
-- Records of play_coupon_code
-- ----------------------------
INSERT INTO `play_coupon_code` VALUES ('23', '000010014', '1', '0', '100140013153', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('24', '000010014', '2', '0', '100140023977', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('25', '000010015', '1', '0', '100150014010', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('26', '000010016', '1', '0', '100160014254', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('27', '000010017', '1', '0', '100170017613', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('28', '000010018', '1', '0', '100180016352', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('29', '000010019', '1', '0', '100190012728', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('30', '000010020', '1', '0', '100200015793', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('31', '000010021', '1', '0', '100210018863', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('32', '000010022', '1', '0', '100220011713', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('33', '000010023', '1', '0', '100230011916', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('34', '000010024', '1', '0', '100240013134', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('35', '000010025', '1', '0', '100250018432', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('36', '000010026', '1', '0', '100260012310', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('37', '000010027', '1', '0', '100270012304', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('38', '000010028', '1', '0', '100280012166', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('39', '000010029', '1', '0', '100290012653', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('40', '000010030', '1', '0', '100300013663', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('41', '000010031', '1', '0', '100310013247', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('42', '000010032', '1', '0', '100320017705', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('43', '000010033', '1', '0', '100330018578', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('44', '000010034', '1', '0', '100340017612', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('45', '000010035', '1', '0', '100350015542', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('46', '000010036', '1', '0', '100360013778', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('47', '000010037', '1', '0', '100370015660', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('48', '000010038', '1', '0', '100380018259', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('49', '000010039', '1', '0', '100390014554', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('50', '000010040', '1', '0', '100400018179', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('51', '000010041', '1', '0', '100410014720', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('52', '000010042', '1', '0', '100420013187', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('53', '000010043', '1', '0', '100430013415', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('54', '000010044', '1', '0', '100440017620', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('55', '000010045', '1', '0', '100450013317', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('56', '000010046', '1', '0', '100460011326', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('57', '000010047', '1', '0', '100470014937', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('58', '000010048', '1', '0', '100480017561', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('59', '000010049', '1', '0', '100490019079', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('60', '000010050', '1', '0', '100500018973', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('61', '000010051', '1', '0', '100510012582', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('62', '000010052', '1', '0', '100520016709', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('63', '000010053', '1', '0', '100530016675', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('64', '000010054', '1', '0', '100540016522', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('65', '000010055', '1', '0', '100550018439', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('66', '000010056', '1', '0', '100560014950', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('67', '000010057', '1', '0', '100570015302', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('68', '000010058', '1', '0', '100580015110', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('69', '000010059', '1', '0', '100590015170', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('70', '000010060', '1', '0', '100600013868', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('71', '000010061', '1', '0', '100610011075', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('72', '000010062', '1', '0', '100620012375', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('73', '000010063', '1', '0', '100630019857', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('74', '000010064', '1', '0', '100640011753', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('75', '000010065', '1', '0', '100650016641', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('76', '000010066', '1', '0', '100660013528', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('77', '000010067', '1', '0', '100670015307', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('78', '000010068', '1', '0', '100680017155', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('79', '000010069', '1', '0', '100690011942', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('80', '000010070', '1', '0', '100700015935', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('81', '000010071', '1', '0', '100710018917', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('82', '000010072', '1', '0', '100720019984', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('83', '000010073', '1', '0', '100730012235', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('84', '000010074', '1', '0', '100740011334', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('85', '000010075', '1', '0', '100750012711', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('86', '000010076', '1', '0', '100760014472', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('87', '000010077', '1', '0', '100770011678', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('88', '000010078', '1', '0', '100780012726', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('89', '000010079', '1', '0', '100790017839', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('90', '000010080', '1', '0', '100800016246', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('91', '000010081', '1', '0', '100810012672', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('92', '000010082', '1', '0', '100820012057', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('93', '000010083', '1', '0', '100830018583', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('94', '000010084', '1', '0', '100840019505', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('95', '000010085', '1', '0', '100850019950', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('96', '000010086', '1', '0', '100860014110', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('97', '000010087', '1', '0', '100870011293', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('98', '000010088', '1', '0', '100880016598', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('99', '000010089', '1', '0', '100890019510', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('100', '000010090', '1', '0', '100900017040', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('101', '000010091', '1', '0', '100910016398', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('102', '000010092', '1', '0', '100920011186', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('103', '000010093', '1', '0', '100930018787', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('104', '000010094', '1', '0', '100940015385', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('105', '000010095', '1', '0', '100950011893', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('106', '000010096', '1', '0', '100960012422', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('107', '000010097', '1', '0', '100970013810', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('108', '000010098', '1', '0', '100980019649', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('109', '000010099', '1', '0', '100990019716', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('110', '000010100', '1', '0', '101000012418', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('111', '000010101', '1', '0', '101010013434', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('112', '000010102', '1', '0', '101020014430', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('113', '000010103', '1', '0', '101030012297', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('114', '000010104', '1', '0', '101040016359', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('115', '000010105', '1', '0', '101050017771', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('116', '000010106', '1', '0', '101060018737', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('117', '000010107', '1', '0', '101070016349', '0', '0');
INSERT INTO `play_coupon_code` VALUES ('118', '000010108', '1', '3', '101080011454', '0', '0');

-- ----------------------------
-- Table structure for `play_coupon_type`
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
-- Records of play_coupon_type
-- ----------------------------

-- ----------------------------
-- Table structure for `play_feedback`
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
) ENGINE=InnoDB AUTO_INCREMENT=75 DEFAULT CHARSET=utf8 COMMENT='意见反馈';

-- ----------------------------
-- Records of play_feedback
-- ----------------------------
INSERT INTO `play_feedback` VALUES ('66', null, null, 'sadgwega', '1408600205', '0', null, '1');
INSERT INTO `play_feedback` VALUES ('67', '235235', null, 'sadgwega', '1408600243', '0', 'sadgwegsag', '1');
INSERT INTO `play_feedback` VALUES ('68', '235235', null, 'sadgwega', '1408600244', '0', 'sadgwegsag', '1');
INSERT INTO `play_feedback` VALUES ('69', '17', null, '啊啊啊啊啊啊啊', '1409407316', '0', '试试', '1');
INSERT INTO `play_feedback` VALUES ('70', '17', null, '“^_^-~……~…“!!,‘“--~~…:!。“！！…～…”～“！…… ～——～“””””””', '1409407410', '0', 'balkaakl', '1');
INSERT INTO `play_feedback` VALUES ('71', '28', null, 'jhvghvfghvb', '1410588593', '0', 'hhbbbj', '1');
INSERT INTO `play_feedback` VALUES ('72', '28', null, 'bhggvh\n', '1410588603', '0', 'jhggff', '1');
INSERT INTO `play_feedback` VALUES ('73', '28', null, '巴巴爸爸巴巴爸爸巴巴爸爸巴巴爸爸', '1410848506', '0', '123456789', '1');
INSERT INTO `play_feedback` VALUES ('74', '18', null, 'gggg', '1410883585', '0', 'yyu,&#039; or 1=2', '1');

-- ----------------------------
-- Table structure for `play_market`
-- ----------------------------
DROP TABLE IF EXISTS `play_market`;
CREATE TABLE `play_market` (
  `market_id` int(5) unsigned zerofill NOT NULL AUTO_INCREMENT COMMENT '商户id',
  `market_name` varchar(255) NOT NULL COMMENT '商家名称',
  `market_type` varchar(60) NOT NULL COMMENT '商家类型',
  `market_city` varchar(60) NOT NULL COMMENT '商家所在城市',
  `market_acr` varchar(10) NOT NULL COMMENT '所在城市 字母',
  `market_status` int(11) NOT NULL DEFAULT '0' COMMENT '商家状态 0 正常 -1 关闭',
  PRIMARY KEY (`market_id`),
  KEY `market_type` (`market_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8 COMMENT='商家';

-- ----------------------------
-- Records of play_market
-- ----------------------------
INSERT INTO `play_market` VALUES ('00023', '贝贝兔DIY蛋糕吧【测试】', '烘焙', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00024', '马可儿童拓展乐园【测试】', '儿童乐园', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00025', '武汉云齐亲子营【测试】', '户外教育', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00026', '我爱泥DIY手工艺术陶吧【测试】', '手工', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00027', '武汉宝林果园【测试】', '农家乐', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00028', '奥山冰雪主题公园【测试】', '运动场馆', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00029', '孩子王乐园【测试】', '儿童乐园', '武汉', 'WH', '0');
INSERT INTO `play_market` VALUES ('00030', '贝乐园【测试】', '儿童乐园', '武汉', 'WH', '0');

-- ----------------------------
-- Table structure for `play_order_action`
-- ----------------------------
DROP TABLE IF EXISTS `play_order_action`;
CREATE TABLE `play_order_action` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单号',
  `play_status` varchar(255) DEFAULT NULL COMMENT '付款状态  支付状态 0未付款; 1已付款; 2退款中 3已退款',
  `action_user` varchar(255) DEFAULT NULL COMMENT '操作员',
  `action_note` varchar(255) DEFAULT NULL COMMENT '操作备注',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`action_id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8 COMMENT='订单操作表';

-- ----------------------------
-- Records of play_order_action
-- ----------------------------
INSERT INTO `play_order_action` VALUES ('73', '10069', '0', '4', '下单成功', '1416812353');
INSERT INTO `play_order_action` VALUES ('74', '10070', '0', '1', '下单成功', '1416814096');
INSERT INTO `play_order_action` VALUES ('75', '10071', '0', '3', '下单成功', '1416814169');
INSERT INTO `play_order_action` VALUES ('76', '10072', '0', '4', '下单成功', '1416814589');
INSERT INTO `play_order_action` VALUES ('77', '10073', '0', '3', '下单成功', '1416814664');
INSERT INTO `play_order_action` VALUES ('78', '10074', '0', '4', '下单成功', '1416815257');
INSERT INTO `play_order_action` VALUES ('79', '10075', '0', '4', '下单成功', '1416815893');
INSERT INTO `play_order_action` VALUES ('80', '10076', '0', '4', '下单成功', '1416815931');
INSERT INTO `play_order_action` VALUES ('81', '10077', '0', '4', '下单成功', '1416816235');
INSERT INTO `play_order_action` VALUES ('82', '10078', '0', '4', '下单成功', '1416816308');
INSERT INTO `play_order_action` VALUES ('83', '10079', '0', '4', '下单成功', '1416816319');
INSERT INTO `play_order_action` VALUES ('84', '10080', '0', '3', '下单成功', '1416816333');
INSERT INTO `play_order_action` VALUES ('85', '10081', '0', '3', '下单成功', '1416816341');
INSERT INTO `play_order_action` VALUES ('86', '10082', '0', '4', '下单成功', '1416816343');
INSERT INTO `play_order_action` VALUES ('87', '10083', '0', '3', '下单成功', '1416816355');
INSERT INTO `play_order_action` VALUES ('88', '10084', '0', '3', '下单成功', '1416816367');
INSERT INTO `play_order_action` VALUES ('89', '10085', '0', '3', '下单成功', '1416816439');
INSERT INTO `play_order_action` VALUES ('90', '10086', '0', '4', '下单成功', '1416816538');
INSERT INTO `play_order_action` VALUES ('91', '10087', '0', '4', '下单成功', '1416816548');
INSERT INTO `play_order_action` VALUES ('92', '10088', '0', '1', '下单成功', '1416816793');
INSERT INTO `play_order_action` VALUES ('93', '10089', '0', '4', '下单成功', '1416816810');
INSERT INTO `play_order_action` VALUES ('94', '10090', '0', '4', '下单成功', '1416816860');
INSERT INTO `play_order_action` VALUES ('95', '10091', '0', '3', '下单成功', '1416816927');
INSERT INTO `play_order_action` VALUES ('96', '10092', '0', '4', '下单成功', '1416816947');
INSERT INTO `play_order_action` VALUES ('97', '10093', '0', '1', '下单成功', '1416816966');
INSERT INTO `play_order_action` VALUES ('98', '10094', '0', '4', '下单成功', '1416817015');
INSERT INTO `play_order_action` VALUES ('99', '10095', '0', '3', '下单成功', '1416817060');
INSERT INTO `play_order_action` VALUES ('100', '10096', '0', '4', '下单成功', '1416817106');
INSERT INTO `play_order_action` VALUES ('101', '10097', '0', '4', '下单成功', '1416817229');
INSERT INTO `play_order_action` VALUES ('102', '10098', '0', '4', '下单成功', '1416817238');
INSERT INTO `play_order_action` VALUES ('103', '10099', '0', '4', '下单成功', '1416817279');
INSERT INTO `play_order_action` VALUES ('104', '10100', '0', '4', '下单成功', '1416817300');
INSERT INTO `play_order_action` VALUES ('105', '10101', '0', '4', '下单成功', '1416817488');
INSERT INTO `play_order_action` VALUES ('106', '10102', '0', '1', '下单成功', '1416817562');
INSERT INTO `play_order_action` VALUES ('107', '10103', '0', '4', '下单成功', '1416817601');
INSERT INTO `play_order_action` VALUES ('108', '10104', '0', '4', '下单成功', '1416817735');
INSERT INTO `play_order_action` VALUES ('109', '10105', '0', '3', '下单成功', '1416817747');
INSERT INTO `play_order_action` VALUES ('110', '10106', '0', '4', '下单成功', '1416817749');
INSERT INTO `play_order_action` VALUES ('111', '10107', '0', '1', '下单成功', '1416817830');
INSERT INTO `play_order_action` VALUES ('112', '10108', '0', '3', '下单成功', '1416818797');

-- ----------------------------
-- Table structure for `play_order_info`
-- ----------------------------
DROP TABLE IF EXISTS `play_order_info`;
CREATE TABLE `play_order_info` (
  `order_sn` int(9) unsigned zerofill NOT NULL AUTO_INCREMENT COMMENT '唯一订单号',
  `coupon_id` int(11) NOT NULL COMMENT '卡券id',
  `order_status` int(11) NOT NULL DEFAULT '1' COMMENT '订单状态,1为正常,0 删除',
  `pay_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '付款状态 ;0未付款;1付款中;2已付款 3  退款中 4 退款成功 5已使用',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `username` varchar(255) NOT NULL COMMENT '用户名称',
  `real_pay` decimal(8,2) DEFAULT NULL COMMENT '真实付款',
  `voucher` decimal(8,2) DEFAULT NULL COMMENT '代金券付款金额',
  `voucher_id` int(11) DEFAULT NULL COMMENT '代金券 id',
  `coupon_unit_price` decimal(8,2) DEFAULT NULL COMMENT '卡券单价',
  `coupon_name` varchar(255) DEFAULT NULL COMMENT '卡券名称',
  `shop_name` varchar(255) DEFAULT NULL COMMENT '商家名称',
  `shop_id` varchar(255) DEFAULT NULL COMMENT '商家id',
  `buy_number` int(11) NOT NULL COMMENT '订单包含的卡券数',
  `use_number` int(11) NOT NULL DEFAULT '0' COMMENT '使用数量',
  `back_number` int(11) NOT NULL DEFAULT '0' COMMENT '退订数',
  `account` varchar(255) DEFAULT NULL COMMENT '支付账号',
  `account_type` enum('weixin','alipay') DEFAULT NULL COMMENT '账号类型',
  `buy_name` varchar(40) NOT NULL COMMENT '购买姓名',
  `buy_phone` varchar(20) NOT NULL COMMENT '购买电话',
  `dateline` int(11) NOT NULL COMMENT '下单时间',
  `use_dateline` int(11) DEFAULT NULL COMMENT '最后使用时间',
  `trade_no` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`order_sn`),
  KEY `pay_status` (`pay_status`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `shop_id` (`shop_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=10109 DEFAULT CHARSET=utf8 COMMENT='订单信息';

-- ----------------------------
-- Records of play_order_info
-- ----------------------------
INSERT INTO `play_order_info` VALUES ('000010014', '11', '1', '2', '1', '王维杰', '156.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '2', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416626757', '0', '123465');
INSERT INTO `play_order_info` VALUES ('000010032', '11', '1', '0', '1', '王维杰', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416800803', '0', null);
INSERT INTO `play_order_info` VALUES ('000010033', '11', '1', '0', '1', '王维杰', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416800959', '0', null);
INSERT INTO `play_order_info` VALUES ('000010034', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416804847', '0', null);
INSERT INTO `play_order_info` VALUES ('000010035', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416804949', '0', null);
INSERT INTO `play_order_info` VALUES ('000010036', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416804991', '0', null);
INSERT INTO `play_order_info` VALUES ('000010037', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416805015', '0', null);
INSERT INTO `play_order_info` VALUES ('000010038', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416805020', '0', null);
INSERT INTO `play_order_info` VALUES ('000010039', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416805027', '0', null);
INSERT INTO `play_order_info` VALUES ('000010040', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416806605', '0', null);
INSERT INTO `play_order_info` VALUES ('000010041', '11', '1', '0', '3', '蒋诗曲', '78.00', '0.00', '0', '78.00', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416806686', '0', null);
INSERT INTO `play_order_info` VALUES ('000010042', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807007', '0', null);
INSERT INTO `play_order_info` VALUES ('000010043', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807072', '0', null);
INSERT INTO `play_order_info` VALUES ('000010044', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807258', '0', null);
INSERT INTO `play_order_info` VALUES ('000010045', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807386', '0', null);
INSERT INTO `play_order_info` VALUES ('000010046', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807723', '0', null);
INSERT INTO `play_order_info` VALUES ('000010047', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807737', '0', null);
INSERT INTO `play_order_info` VALUES ('000010048', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807751', '0', null);
INSERT INTO `play_order_info` VALUES ('000010049', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807799', '0', null);
INSERT INTO `play_order_info` VALUES ('000010050', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416807809', '0', null);
INSERT INTO `play_order_info` VALUES ('000010051', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808085', '0', null);
INSERT INTO `play_order_info` VALUES ('000010052', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808356', '0', null);
INSERT INTO `play_order_info` VALUES ('000010053', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808464', '0', null);
INSERT INTO `play_order_info` VALUES ('000010054', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808734', '0', null);
INSERT INTO `play_order_info` VALUES ('000010055', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808754', '0', null);
INSERT INTO `play_order_info` VALUES ('000010056', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416808845', '0', null);
INSERT INTO `play_order_info` VALUES ('000010057', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809180', '0', null);
INSERT INTO `play_order_info` VALUES ('000010058', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809187', '0', null);
INSERT INTO `play_order_info` VALUES ('000010059', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809210', '0', null);
INSERT INTO `play_order_info` VALUES ('000010060', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809244', '0', null);
INSERT INTO `play_order_info` VALUES ('000010061', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809310', '0', null);
INSERT INTO `play_order_info` VALUES ('000010062', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809376', '0', null);
INSERT INTO `play_order_info` VALUES ('000010063', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809605', '0', null);
INSERT INTO `play_order_info` VALUES ('000010064', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809635', '0', null);
INSERT INTO `play_order_info` VALUES ('000010065', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809718', '0', null);
INSERT INTO `play_order_info` VALUES ('000010066', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416809896', '0', null);
INSERT INTO `play_order_info` VALUES ('000010067', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416810440', '0', null);
INSERT INTO `play_order_info` VALUES ('000010068', '11', '1', '0', '3', '蒋诗曲', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '蒋诗曲', '15871725222', '1416810598', '0', null);
INSERT INTO `play_order_info` VALUES ('000010069', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416812353', '0', null);
INSERT INTO `play_order_info` VALUES ('000010070', '11', '1', '0', '1', '王维杰', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416814096', '0', null);
INSERT INTO `play_order_info` VALUES ('000010071', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416814169', '0', null);
INSERT INTO `play_order_info` VALUES ('000010072', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416814589', '0', null);
INSERT INTO `play_order_info` VALUES ('000010073', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416814664', '0', null);
INSERT INTO `play_order_info` VALUES ('000010074', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416815257', '0', null);
INSERT INTO `play_order_info` VALUES ('000010075', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416815893', '0', null);
INSERT INTO `play_order_info` VALUES ('000010076', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416815931', '0', null);
INSERT INTO `play_order_info` VALUES ('000010077', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816235', '0', null);
INSERT INTO `play_order_info` VALUES ('000010078', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816307', '0', null);
INSERT INTO `play_order_info` VALUES ('000010079', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816319', '0', null);
INSERT INTO `play_order_info` VALUES ('000010080', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816332', '0', null);
INSERT INTO `play_order_info` VALUES ('000010081', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816341', '0', null);
INSERT INTO `play_order_info` VALUES ('000010082', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816343', '0', null);
INSERT INTO `play_order_info` VALUES ('000010083', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816355', '0', null);
INSERT INTO `play_order_info` VALUES ('000010084', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816367', '0', null);
INSERT INTO `play_order_info` VALUES ('000010085', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816439', '0', null);
INSERT INTO `play_order_info` VALUES ('000010086', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816538', '0', null);
INSERT INTO `play_order_info` VALUES ('000010087', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816547', '0', null);
INSERT INTO `play_order_info` VALUES ('000010088', '11', '1', '0', '1', '王维杰', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416816793', '0', null);
INSERT INTO `play_order_info` VALUES ('000010089', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816810', '0', null);
INSERT INTO `play_order_info` VALUES ('000010090', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816860', '0', null);
INSERT INTO `play_order_info` VALUES ('000010091', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416816927', '0', null);
INSERT INTO `play_order_info` VALUES ('000010092', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416816947', '0', null);
INSERT INTO `play_order_info` VALUES ('000010093', '11', '1', '0', '1', '王维杰', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416816966', '0', null);
INSERT INTO `play_order_info` VALUES ('000010094', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817015', '0', null);
INSERT INTO `play_order_info` VALUES ('000010095', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416817060', '0', null);
INSERT INTO `play_order_info` VALUES ('000010096', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817106', '0', null);
INSERT INTO `play_order_info` VALUES ('000010097', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817229', '0', null);
INSERT INTO `play_order_info` VALUES ('000010098', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817238', '0', null);
INSERT INTO `play_order_info` VALUES ('000010099', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817279', '0', null);
INSERT INTO `play_order_info` VALUES ('000010100', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817299', '0', null);
INSERT INTO `play_order_info` VALUES ('000010101', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817488', '0', null);
INSERT INTO `play_order_info` VALUES ('000010102', '11', '1', '0', '1', '王维杰', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416817562', '0', null);
INSERT INTO `play_order_info` VALUES ('000010103', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817601', '0', null);
INSERT INTO `play_order_info` VALUES ('000010104', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817734', '0', null);
INSERT INTO `play_order_info` VALUES ('000010105', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '强力手_653', '15871725222', '1416817747', '0', null);
INSERT INTO `play_order_info` VALUES ('000010106', '11', '1', '0', '4', '严铭', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '严铭', '18627792275', '1416817749', '0', null);
INSERT INTO `play_order_info` VALUES ('000010107', '11', '1', '0', '1', '王维杰', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '0', '0', '', 'alipay', '王维杰', '15994225894', '1416817830', '0', null);
INSERT INTO `play_order_info` VALUES ('000010108', '11', '1', '0', '3', '强力手_653', '0.01', '0.00', '0', '0.01', '水上乐园', '贝乐园【测试】', '30', '1', '1', '0', '', 'alipay', '强力手_653', '15871725222', '1416818797', '0', null);

-- ----------------------------
-- Table structure for `play_region`
-- ----------------------------
DROP TABLE IF EXISTS `play_region`;
CREATE TABLE `play_region` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rid` varchar(20) NOT NULL COMMENT '地区编码',
  `name` varchar(20) NOT NULL COMMENT '商圈名称',
  `acr` varchar(10) DEFAULT 'WH',
  PRIMARY KEY (`id`),
  KEY `rid` (`rid`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COMMENT='商圈';

-- ----------------------------
-- Records of play_region
-- ----------------------------
INSERT INTO `play_region` VALUES ('2', '42011100', '江岸区', 'WH');
INSERT INTO `play_region` VALUES ('6', '42011200', '洪山区', 'WH');
INSERT INTO `play_region` VALUES ('7', '42011300', '江汉区', 'WH');
INSERT INTO `play_region` VALUES ('8', '42011101', '客运港/江滩', 'WH');
INSERT INTO `play_region` VALUES ('9', '42011102', '台北路/香港路', 'WH');
INSERT INTO `play_region` VALUES ('10', '42011201', '光谷/鲁巷', 'WH');
INSERT INTO `play_region` VALUES ('11', '42011202', '石牌岭/街道口', 'WH');
INSERT INTO `play_region` VALUES ('12', '42011301', '江汉路步行街', 'WH');
INSERT INTO `play_region` VALUES ('13', '42011302', '武汉广场', 'WH');

-- ----------------------------
-- Table structure for `play_share`
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
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='分享记录表';

-- ----------------------------
-- Records of play_share
-- ----------------------------
INSERT INTO `play_share` VALUES ('17', '12', 'shop', '12', '1415677957');
INSERT INTO `play_share` VALUES ('18', '12', 'shop', '12', '1415695379');

-- ----------------------------
-- Table structure for `play_shop`
-- ----------------------------
DROP TABLE IF EXISTS `play_shop`;
CREATE TABLE `play_shop` (
  `shop_id` int(6) NOT NULL AUTO_INCREMENT COMMENT '店铺id',
  `shop_mid` int(5) unsigned zerofill NOT NULL COMMENT 'shop  父id  market_id',
  `shop_type` int(11) NOT NULL COMMENT '店铺类型',
  `shop_city` varchar(30) NOT NULL COMMENT '店铺所在城市',
  `shop_acr` varchar(6) NOT NULL COMMENT '城市名称缩写',
  `shop_address` varchar(255) NOT NULL COMMENT '地址',
  `addr_x` varchar(255) NOT NULL COMMENT '经度坐标',
  `addr_y` varchar(255) NOT NULL COMMENT '纬度坐标',
  `shop_phone` varchar(255) NOT NULL COMMENT '电话',
  `busniess_circle` varchar(255) NOT NULL COMMENT '商圈',
  `shop_name` varchar(255) NOT NULL COMMENT '店铺名称',
  `shop_open` int(11) NOT NULL COMMENT '营业时间 开始',
  `shop_close` int(11) NOT NULL COMMENT '营业时间 结束',
  `shop_status` int(11) NOT NULL DEFAULT '0' COMMENT '店铺状态 0正常状态 -1关闭',
  PRIMARY KEY (`shop_id`),
  KEY `addr_x` (`addr_x`) USING BTREE,
  KEY `addr_y` (`addr_y`) USING BTREE,
  KEY `addr_xy` (`addr_x`,`addr_y`) USING BTREE,
  KEY `acr_city` (`shop_city`,`shop_acr`) USING BTREE,
  KEY `shop_name` (`shop_name`) USING BTREE,
  KEY `shop_status` (`shop_status`) USING BTREE,
  KEY `shop_type` (`shop_type`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8 COMMENT='店铺表';

-- ----------------------------
-- Records of play_shop
-- ----------------------------
INSERT INTO `play_shop` VALUES ('7', '00023', '0', '武汉', 'WH', '江汉区解放大道557号万松园路口中山广场2407室（武汉广场对面苏宁电器楼上）', '114.276336', '30.587483', '13607123381', '42011101', '贝贝兔', '1416790800', '1416837600', '0');
INSERT INTO `play_shop` VALUES ('8', '00024', '0', '武汉', 'WH', '洪山区关山街道珞喻路光谷世界城广场4楼', '114.412789', '30.511146', '027-87106800', '42011201', '马可儿童拓展乐园【测试】', '1416790800', '1416830400', '0');
INSERT INTO `play_shop` VALUES ('9', '00025', '0', '武汉', 'WH', '洪山区洪福添美广场2栋 ', '114.404731', '30.508812', '13554314960', '42011202', '武汉云齐亲子营【测试】', '1416790800', '1416837600', '0');
INSERT INTO `play_shop` VALUES ('10', '00026', '0', '武汉', 'WH', '武昌区昙华林55号（瑞典教区旁）', '114.404731', '30.508812', '13027188803', '42011201', '我爱泥DIY手工艺术陶吧【测试】', '1416790800', '1416837600', '0');
INSERT INTO `play_shop` VALUES ('11', '00027', '0', '武汉', 'WH', '黄陂区木兰乡静山村（李先念故居旁）', '114.506869', '31.141113', '18502789768', '42011201', '武汉宝林果园【测试】', '1416790800', '1416834000', '0');
INSERT INTO `play_shop` VALUES ('12', '00028', '0', '武汉', 'WH', '青山区和平大道奥山世纪广场三楼', '114.367056', '30.621628', '027-86850199', '42011102', '奥山冰雪主题公园【测试】', '1416794400', '1416823200', '0');
INSERT INTO `play_shop` VALUES ('13', '00029', '0', '武汉', 'WH', '武昌和平大道819号奥山世纪城2F', '114.366454', '30.625046', '027-87888883 ', '42011100', '孩子王童乐园奥山世纪城店【测试】', '1416794400', '1416834000', '0');
INSERT INTO `play_shop` VALUES ('14', '00029', '0', '武汉', 'WH', '建设大道和后湖大道交汇处汉口城市广场', '114.295165', '30.6439', '027-87888883 ', '42011101', '孩子王童乐园（后湖城市广场）【测试】', '1416794400', '1416841200', '0');
INSERT INTO `play_shop` VALUES ('15', '00029', '0', '武汉', 'WH', '沌口东风大道经开万达广场3楼', '114.180028', '30.513036', '027-87888883', '42011202', '孩子王童乐园（沌口 万达）【测试】', '1416794400', '1416837600', '0');
INSERT INTO `play_shop` VALUES ('16', '00030', '0', '武汉', 'WH', '江汉区武汉广场7楼贝乐园儿童游乐园', '114.276329', '30.58669', '027-84660669', '42011100', '贝乐园（武广）【测试】', '1416794400', '1416830400', '0');
INSERT INTO `play_shop` VALUES ('17', '00030', '0', '武汉', 'WH', '江汉区 国际广场6楼贝乐园儿童游乐园', '114.262503', '30.612599', '027-84660669', '42011202', '贝乐园', '1416794400', '1416834000', '0');
INSERT INTO `play_shop` VALUES ('18', '00030', '0', '武汉', 'WH', '汉阳汉阳区龙阳大道6号摩尔城B栋4楼', '114.213148', '30.564832', '027-84660669', '42011201', '贝乐园（汉阳摩尔城店）【测试】', '1416794400', '1416841200', '0');

-- ----------------------------
-- Table structure for `play_user`
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
  PRIMARY KEY (`uid`),
  KEY `mark_info` (`mark_info`,`username`,`login_type`,`is_online`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='用户主表';

-- ----------------------------
-- Records of play_user
-- ----------------------------
INSERT INTO `play_user` VALUES ('1', '24', null, '0', null, '23', '23', '23', '23', '23', '', null, '0', '1', null);
INSERT INTO `play_user` VALUES ('2', 'wang', null, '0', null, '', '', null, '', '0', 'android', null, '0', '1', null);
INSERT INTO `play_user` VALUES ('3', '强力手_653', null, '0', null, '2.00SBw3yCLR3iAEce5643b118SXSEqD', '2724312072', '15871725222', 'sinaweibo', '1', 'android', '1000', '1416561021', '1', 'http://tp1.sinaimg.cn/2724312072/50/5643422052/1');
INSERT INTO `play_user` VALUES ('4', '、', null, '0', null, '3281E5BD1B39BD6FAEC40FBB1DD6DB55', '4AB94B9035C467E5D891E84A67743C1A', '18627792275', 'qq', '1', 'ios', '1000', '1416816532', '1', 'http://q.qlogo.cn/qqapp/1103475928/4AB94B9035C467E5D891E84A67743C1A/100');
INSERT INTO `play_user` VALUES ('5', '15994225894', 'bf4165179739a35ffab0a63e2b843f56', '0', null, '0', '0', '15994225894', 'phone', '1', 'android', null, '1416813328', '1', null);

-- ----------------------------
-- Table structure for `play_user_linker`
-- ----------------------------
DROP TABLE IF EXISTS `play_user_linker`;
CREATE TABLE `play_user_linker` (
  `linker_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '订单联系人 id',
  `user_id` int(11) NOT NULL COMMENT '用户id',
  `linker_name` varchar(50) NOT NULL COMMENT '联系人姓名',
  `linker_phone` varchar(30) NOT NULL COMMENT '联系人 电话',
  PRIMARY KEY (`linker_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='用户手机号列表';

-- ----------------------------
-- Records of play_user_linker
-- ----------------------------
INSERT INTO `play_user_linker` VALUES ('2', '1', 'hello', '3426326');
INSERT INTO `play_user_linker` VALUES ('3', '1', 'hello', '3426326');
INSERT INTO `play_user_linker` VALUES ('4', '1', 'hello', '3426326');
INSERT INTO `play_user_linker` VALUES ('5', '1', 'hello', '3426326');
