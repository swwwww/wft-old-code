<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use library\Fun\M;
use library\Fun\OutPut;
use library\Service\System\Cache\RedCache;
use library\Service\System\Paginator\Paginator;


class H5statisticsController extends BasisController
{
    use JsonResponse;

    //统计渠道充值
    public function indexAction()
    {

        $db = M::getAdapter();
        $out = (int)$_GET['out'];
        if ($_GET['sign_start']) {
            $start_time = strtotime($_GET['sign_start']);
        } else {
            $start_time = 0;
            $_GET['sign_start'] = '2016-01-01';
        }

        if ($_GET['sign_end']) {
            $end_time = strtotime($_GET['sign_end']) + 86400;
        } else {
            $end_time = 9479974407;
            $_GET['sign_end'] = date('Y-m-d');
        }

        //取出所有存在数据的渠道
        $res = $db->query("select  `channel` from  play_vip_channel_count  WHERE  dateline >=? and dateline <=? GROUP BY `channel`", array($start_time, $end_time))->toArray();

        //统计对应的数据

        foreach ($res as $k => $v) {
            $res[$k]['all_money'] = M::getAdapter()->query("SELECT (sum(vip_688)*688+sum(vip_988)*988+sum(vip_1688)*1688) as all_money	 FROM 	play_vip_channel_count WHERE channel =? AND  dateline >=? and dateline <=?", array($v['channel'], $start_time, $end_time))->current()->all_money;
            //688元
            $res[$k]['all_688'] = M::getAdapter()->query("SELECT sum(vip_688) as vip_688	 FROM 	play_vip_channel_count WHERE channel =? AND  dateline >=? and dateline <=?", array($v['channel'], $start_time, $end_time))->current()->vip_688;
            //988元
            $res[$k]['all_988'] = M::getAdapter()->query("SELECT sum(vip_988) as vip_988	 FROM 	play_vip_channel_count WHERE channel =? AND  dateline >=? and dateline <=?", array($v['channel'], $start_time, $end_time))->current()->vip_988;
            //1688元
            $res[$k]['all_1688'] = M::getAdapter()->query("SELECT sum(vip_1688) as vip_1688	 FROM 	play_vip_channel_count WHERE channel =? AND  dateline >=? and dateline <=?", array($v['channel'], $start_time, $end_time))->current()->vip_1688;;
            $res[$k]['count'] = $res[$k]['all_688'] + $res[$k]['all_988'] + $res[$k]['all_1688'];
        }

        if ($out == 1) {
            OutPut::out('线上官方渠道充值统计' . $_GET['sign_start'] . '-' . $_GET['sign_end'] . '.csv', array('分销id', '充值总金额', '充值数量', '688元', '988元', '1688元'), $res);
            exit;
        }

        return array(
            'data' => $res
        );
    }

    //统计分销员 from_uid
    public function fenxiaoAction()
    {
        $data_type = $_GET['action_type'] ? $_GET['action_type'] : 1;
        if ($_GET['sign_start']) {
            $start_time = strtotime($_GET['sign_start']);
        } else {
            $start_time = 0;
            $_GET['sign_start'] = '2016-01-01';
        }

        if ($_GET['sign_end']) {
            $end_time = strtotime($_GET['sign_end']) + 86400;
        } else {
            $end_time = 9479974407;
            $_GET['sign_end'] = date('Y-m-d');
        }
        $list = (int)$_GET['list'];
        if ($list == 2) {
            //外部分销员
            $user_list = array(
                198631,
                198630,
                198629,
                198628,
                198627,
                198626,
                198057,
                196803,
                196802,
                196801,
                196800,
                196799,
                196798,
                196081,
                196024,
                196023,
                196022,
                195897,
                195896,
                195592,
                195553,
                195552,
                195551,
                195550,
                195549,
                195548,
                192518,
                190335,
                190330,
                190328,
                190051,
                190050,
                190049,
                190048,
                189675,
                189674,
                189501,
                189500,
                189499,
                189498,
                189497,
                189491,
                189490,
                189489,
                189488,
                189487,
                189459,
                189458,
                189457,
                189448,
                187551,
                187550,
                187548,
                187547,
                187546,
                187545,
                187544,
                187541,
                187539,
                187538,
                187537,
                187536,
                187535,
                187534,
                187532,
                187514,
                186963,
                186962,
                186961,
                186543,
                186542,
                186541,
                186540,
                186539,
                186538,
                186537,
                185246,
                184463,
                184462,
                184461,
                184459,
                184458,
                184457,
                184456,
                184455,
                184454,
                184453,
                184452,
                184261,
                184233,
                184215,
                184214,
                184213,
                184210,
                184209,
                183884,
                183880,
                181429,
                181428,
                181427,
                '晚报' => 184232,
                '晨报' => 184225,
                '纸质媒体' => 184216
            );
        } elseif ($list == 3) {
            //特殊列表
            $user_list = array(
                10025,
                125609,
                138172,
                170155,
                151879
            );
        } else {
            $user_list = array(
                '|客服组|刘艳|' => 155005,
                '|客服组|江晨曦|' => 195890,
                '|客服组|罗露|' => 157063,
                '|客服 |杨雪|' => 60594,
                '|客服组|舒榕|' => 13160,
                '|渠道组|苏娟|' => 41376,
                '|渠道组|徐晶|' => 202650,
                '|渠道组|王炳臣|' => 195174,
                '|编辑组|余淑君|' => 54399,
                '|编辑组|毕博采|' => 151996,
                '|编辑组|方丽娟|' => 10019,
                '|商务|李娜|' => 144861,
                '|商务|彭琳|' => 31336,
                '|推广|王双丽|' => 10007,
                '|推广|梁冰雪|' => 157675,
                '|商务|王炜|' => 44982,
                '|商务|张琦|' => 47430,
                '|商务|胡力' => 218898,
                '|人力资源部|李阳|' => 14893,
                '|人力资源部|卢倩|' => 170399,
                '|策划|冯婷婷|' => 36370,
                '|策划|朱敏|' => 166808,
                '|策划|龙凤|' => 150276,
                '|策划|李美姗|' => 160707,
                '|策划|楚媛|' => 160882,
                '|策划|刘江河|' => 169302,
                '|策划|高怡婧|' => 199043,
                '|品控|黄晶|' => 158579,
                '|品控|徐娜|' => 150188,
                '|品控|谢雪婷|' => 219127,
                '|品控|程王丽|' => 190250,
                '|行政|张辛|' => 40177,
                '|行政|范鹏|' => 195128,
                '|遛娃师|陈必诚|' => 85899,
                '|遛娃师|郑炜|' => 168276,
                '|遛娃师|周玥|' => 219126,
                '|遛娃师|许博文|' => 202936,
                '|遛娃师|明志远|' => 204534,
                '|遛娃师|李小卫|' => 207244,
                '|遛娃师|王起明|' => 218757,
                '|线上运营|朱佳琪|' => 30218,
                '|技术|沈伟|' => 142919,
                '|技术|林飞浪|' => 179497,
                '|技术|王维杰|' => 10023,
                '|技术|李佳|' => 39125,
                '|技术|毕明君|' => 169280,
                '|技术|陈铁|' => 23874,
                '|技术|钟加仁|' => 23989,
                '|技术|万江|' => 10013,
                '|技术|周贝|' => 137151,
                '|技术|聂锐|' => 59810,
                '|技术|徐行贤|' => 145090,
                '|技术|覃涛|' => 152636,
                '|技术|佟鑫|' => 193936,
                '|技术|代寅|' => 11771,
                '|技术|郑艳|' => 186603,
                '|总经理|陈国庆|' => 10000,
                '|运营总监|彭文|' => 10028
            );
        }

        //统计对应的数据

        $res = RedCache::fromCacheData('D:fenxiao_tmp' . $list . $start_time . $end_time, function () use ($user_list, $start_time, $end_time) {
            return $this->getfenxiaoData($user_list, $start_time, $end_time);
        }, 3600, true);

        if ($data_type == '2') {
            OutPut::out('分销数据统计' . $_GET['sign_start'] . '-' . $_GET['sign_end'] . '.csv', array('分销id', '充值数量', '充值总金额', '688元', '988元', '1688元'), $res);
            exit;
        }

        return array(
            'data' => $res
        );
    }


    public function getfenxiaoData($user_list, $start_time, $end_time)
    {

        $res = array();
        foreach ($user_list as $k => $v) {

            $res[$k]['channel'] = $v;


            //充值总数量  取出渠道所有用户 连出 金额大于 688 的数据


            $all_count = M::getAdapter()->query("SELECT
	uid,flow_money,money_service_id
FROM
play_account_log
WHERE
	play_account_log.from_uid =?
	and  play_account_log.action_type=1
AND play_account_log.status=1 AND play_account_log.money_service_id!=0 AND play_account_log.dateline>? and play_account_log.dateline<? GROUP BY play_account_log.id ", array($v, $start_time, $end_time))->toArray();


            $res[$k]['count'] = count($all_count);

            //充值总金额
            $all_money = 0;
            foreach ($all_count as $vv) {
                $all_money = bcadd($all_money, $vv['flow_money'], 2);
            }

            $res[$k]['all_money'] = $all_money;

            //688元
            $c = 0;
            foreach ($all_count as $vv) {
                if ($vv['money_service_id'] == 1) {
                    $c += 1;
                }
            }
            $res[$k]['all_688'] = $c;
            //988元
            $c = 0;
            foreach ($all_count as $vv) {
                if ($vv['money_service_id'] == 2) {
                    $c += 1;
                }
            }
            $res[$k]['all_988'] = $c;

            //1688元
            $c = 0;
            foreach ($all_count as $vv) {
                if ($vv['money_service_id'] == 3) {
                    $c += 1;
                }

            }
            $res[$k]['all_1688'] = $c;

        }

        return $res;
    }

    public function memberMoneyServiceCountAction()
    {
        $param['device_type'] = $this->getQuery('device_type', 0);
        $param['action_type'] = $this->getQuery('action_type', 0);
        $param['from_uid_type'] = $this->getQuery('from_uid_type', 0);
        $param['from_uid'] = $this->getQuery('from_uid', 0);
        $param['money_service_id'] = $this->getQuery('money_service_id', 0);
        $param['start_time'] = $this->getQuery('start_time', '2016-11-01');
        $param['end_time'] = $this->getQuery('end_time', date('Y-m-d H:i:s'));
        $param['page'] = $this->getQuery('page', 1);
        $param['page_num'] = $this->getQuery('page_num', 20);
        $param['type'] = $this->getQuery('type', 1);
        $param['city'] = $this->getAdminCity();

        $data_start = ($param['page'] - 1) * $param['page_num'];

        $sql_where = " play_account_log.action_type = 1 AND play_account_log.status = 1 AND play_account_log.city = '{$param['city']}' ";

        if ($param['device_type'] == 1) {
            $sql_where .= ' AND play_user.device_type = "android" ';
        } else if ($param['device_type'] == 2) {
            $sql_where .= ' AND play_user.device_type = "ios" ';
        } else if ($param['device_type'] == 3) {
            $sql_where .= ' AND (play_user.device_type = "" or play_user.device_type is null) ';
        }

        if ($param['action_type'] == 1) {
            $sql_where .= ' AND play_account_log.action_type_id = 2 ';
        } else if ($param['action_type'] == 2) {
            $sql_where .= ' AND play_account_log.action_type_id = 3 ';
        } else if ($param['action_type'] == 3) {
            $sql_where .= ' AND play_account_log.action_type_id = 12 ';
        } else if ($param['action_type'] == 4) {
            $sql_where .= ' AND play_account_log.action_type_id = 25 ';
        }

        if ($param['from_uid_type'] == 3) {
            if ($param['from_uid']) {
                $sql_where .= ' AND play_account_log.from_uid = ' . $param['from_uid'] . ' ';
            }
        } else if ($param['from_uid_type'] == 2) {
            $sql_where .= ' AND play_account_log.from_uid > 0 ';
        } else if ($param['from_uid_type'] == 1) {
            $sql_where .= ' AND play_account_log.from_uid = 0 ';
        } else if ($param['type'] == 3) {
            $sql_where .= ' AND (play_member.member_level = 0 or play_member.member_level is null) AND play_account_log.from_uid > 0 ';
        }

        if ($param['money_service_id']) {
            $sql_where .= ' AND play_account_log.money_service_id = ' . $param['money_service_id'] . ' ';
        } else {
            $sql_where .= ' AND play_account_log.money_service_id > 0 ';
        }

        if ($param['start_time']) {
            $sql_where .= " AND play_account_log.dateline >= " . strtotime($param['start_time']) . ' ';
        }

        if ($param['end_time']) {
            $sql_where .= " AND play_account_log.dateline <= " . strtotime($param['end_time']) . ' ';
        }

        $pdo = M::getAdapter();

        if ($param['type'] == 2) {
            // 导出数据
            $sql = "
                SELECT
                    play_user.uid,
                    play_user.phone,
                    play_account_log.dateline,
                    play_account_log.flow_money,
                    play_account_log.action_type_id
                FROM
                    play_account_log
                LEFT JOIN play_user ON play_user.uid = play_account_log.uid
                LEFT JOIN play_member_money_service ON play_member_money_service.money_service_id = play_account_log.money_service_id
                WHERE 
                    {$sql_where}
                ORDER BY play_account_log.id DESC                
            ";

            $data = $pdo->query($sql, [])->toArray();
            $data_temp = array();
            foreach ($data as $key => $val) {
                switch ($val['action_type_id']) {
                    case 2 :
                        $data_pay_type = '支付宝';
                        break;
                    case 3 :
                        $data_pay_type = '银联支付';
                        break;
                    case 12:
                        $data_pay_type = '微信钱包';
                        break;
                    case 25:
                        $data_pay_type = '微信网页';
                        break;
                    default:
                        $data_pay_type = '其他方式';
                        break;
                }

                $data_temp[] = array(
                    $val['uid'],
                    $val['phone'],
                    date('Y-m-d H:i:s', $val['dateline']),
                    $val['flow_money'],
                    $data_pay_type
                );
            }

            $data_head = array(
                '用户uid', '手机号', '充值时间', '充值金额', '支付方式'
            );

            if (!$param['start_time']) {
                $param['start_time'] = '2016-11-01';
            }

            if (!$param['end_time']) {
                $param['end_time'] = '今天';
            }
            OutPut::out('会员充值统计' . $param['start_time'] . '到' . $param['end_time'] . '.csv', $data_head, $data_temp);
            exit;
        } else if ($param['type'] == 3) {
            $user_list = array(
                '|客服组|刘艳|' => 155005,
                '|客服组|江晨曦|' => 195890,
                '|客服组|罗露|' => 157063,
                '|客服组|舒榕|' => 13160,
                '|渠道组|苏娟|' => 41376,
                '|渠道组|徐晶|' => 202650,
                '|渠道组|王炳臣|' => 195174,
                '|编辑组|余淑君|' => 54399,
                '|编辑组|毕博采|' => 151996,
                '|编辑组|方丽娟|' => 10019,
                '|商务|李娜|' => 144861,
                '|商务|彭琳|' => 31336,
                '|推广|王双丽|' => 10007,
                '|推广|梁冰雪|' => 157675,
                '|商务|王炜|' => 44982,
                '|商务|张琦|' => 47430,
                '|商务|胡力' => 218898,
                '|人力资源部|李阳|' => 14893,
                '|人力资源部|卢倩|' => 170399,
                '|策划|冯婷婷|' => 36370,
                '|策划|朱敏|' => 166808,
                '|策划|龙凤|' => 150276,
                '|策划|李美姗|' => 160707,
                '|策划|楚媛|' => 160882,
                '|策划|刘江河|' => 169302,
                '|策划|高怡婧|' => 199043,
                '|品控|黄晶|' => 158579,
                '|品控|徐娜|' => 150188,
                '|品控|谢雪婷|' => 219127,
                '|品控|程王丽|' => 190250,
                '|行政|张辛|' => 40177,
                '|行政|范鹏|' => 195128,
                '|遛娃师|陈必诚|' => 85899,
                '|遛娃师|郑炜|' => 168276,
                '|遛娃师|周玥|' => 219126,
                '|遛娃师|许博文|' => 202936,
                '|遛娃师|明志远|' => 204534,
                '|遛娃师|李小卫|' => 207244,
                '|遛娃师|王起明|' => 218757,
                '|线上运营|朱佳琪|' => 30218,
                '|技术|沈伟|' => 142919,
                '|技术|林飞浪|' => 179497,
                '|技术|王维杰|' => 10023,
                '|技术|李佳|' => 39125,
                '|技术|毕明君|' => 169280,
                '|技术|陈铁|' => 23874,
                '|技术|钟加仁|' => 23989,
                '|技术|万江|' => 10013,
                '|技术|周贝|' => 137151,
                '|技术|聂锐|' => 59810,
                '|技术|徐行贤|' => 148462,
                '|技术|覃涛|' => 152636,
                '|技术|佟鑫|' => 193936,
                '|技术|代寅|' => 11771,
                '|技术|郑艳|' => 186603,
                '|总经理|陈国庆|' => 10000,
                '|运营总监|彭文|' => 10028
            );

            // 导出数据
            $sql = "
                SELECT
                    play_user.uid,
                    play_user.phone,
                    play_account_log.dateline,
                    play_account_log.flow_money,
                    play_account_log.action_type_id,
                    play_account_log.from_uid
                FROM
                    play_account_log
                LEFT JOIN play_user ON play_user.uid = play_account_log.uid
                LEFT JOIN play_member ON play_member.member_user_id = play_account_log.from_uid
                LEFT JOIN play_member_money_service ON play_member_money_service.money_service_id = play_account_log.money_service_id
                WHERE 
                    {$sql_where}
                ORDER BY play_account_log.id DESC                
            ";

            $data = $pdo->query($sql, [])->toArray();
            $data_temp = array();
            foreach ($data as $key => $val) {
                if (in_array($val['from_uid'], $user_list)) {
                    continue;
                }

                switch ($val['action_type_id']) {
                    case 2 :
                        $data_pay_type = '支付宝';
                        break;
                    case 3 :
                        $data_pay_type = '银联支付';
                        break;
                    case 12:
                        $data_pay_type = '微信钱包';
                        break;
                    case 25:
                        $data_pay_type = '微信网页';
                        break;
                    default:
                        $data_pay_type = '其他方式';
                        break;
                }

                $data_temp[] = array(
                    $val['uid'],
                    $val['phone'],
                    date('Y-m-d H:i:s', $val['dateline']),
                    $val['flow_money'],
                    $data_pay_type,
                    $val['from_uid']
                );
            }

            $data_head = array(
                '用户uid', '手机号', '充值时间', '充值金额', '支付方式', '来自于哪个用户'
            );

            if (!$param['start_time']) {
                $param['start_time'] = '2016-11-01';
            }

            if (!$param['end_time']) {
                $param['end_time'] = '今天';
            }
            OutPut::out('会员充值统计' . $param['start_time'] . '到' . $param['end_time'] . '.csv', $data_head, $data_temp);
            exit;
        } else {
            $sql = "
                SELECT
                    play_user.uid,
                    play_user.phone,
                    play_account_log.dateline,
                    play_account_log.flow_money,
                    play_account_log.action_type_id
                FROM
                    play_account_log
                LEFT JOIN play_user ON play_user.uid = play_account_log.uid
                LEFT JOIN play_member_money_service ON play_member_money_service.money_service_id = play_account_log.money_service_id
                WHERE 
                    {$sql_where}
                ORDER BY id DESC
                LIMIT {$data_start}, {$param['page_num']}
            ";
            $data = $pdo->query($sql, [])->toArray();

            $sql = "
                SELECT
                    count(*) as c
                FROM
                    play_account_log
                LEFT JOIN play_user ON play_user.uid = play_account_log.uid
                LEFT JOIN play_member_money_service ON play_member_money_service.money_service_id = play_account_log.money_service_id
                WHERE 
                    {$sql_where}
            ";

            $count = $pdo->query($sql, [])->current()->c;

            //创建分页
            $url = '/wftadlogin/h5statistics/membermoneyservicecount';
            $data_page = new Paginator($param['page'], $count, $param['page_num'], $url);

            return array(
                'data' => $data,
                'data_page' => $data_page->getHtml(),
            );
        }
    }
}
