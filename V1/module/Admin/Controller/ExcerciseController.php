<?php
namespace Admin\Controller;

use Deyi\Baoyou\Baoyou;
use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\ExcerciseCache;
use Deyi\GetCacheData\UserCache;
use Deyi\Idverification;
use Deyi\JsonResponse;
use Deyi\GetCacheData\PlaceCache;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OrderAction\UseExcerciseCode;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\PY;
use Deyi\SendMessage;

use library\Fun\M;
use library\Service\Admin\Setting\IndexBlock;
use library\Service\Kidsplay\Kidsplay;
use library\Service\System\Logger;
use library\Service\User\Member;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ViewModel;


class ExcerciseController extends BasisController
{

    //use BaseController;
    use JsonResponse;

    private $project_name = [1 => '成人', 2 => '儿童1.2m~1.4m', 3 => '儿童<1.2m', 4 => '儿童', 99 => '自定义'];
    //private $teacher = [6=>'黄金遛娃师',5=>'白金遛娃师',4=>'金牌遛娃师',3=>'遛娃师(配教)'];
    private $teacher = [4 => '金牌遛娃师'];

    //场次列表
    public function indexAction()
    {
        //创建分页
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $start = ($start < 0) ? 0 : $start;

        $name = $this->getQuery('name', '');
        $start_time = $this->getQuery('start_time', 0);
        $end_time = $this->getQuery('end_time', 0);
        $bid = (int)$this->getQuery('bid', 0);
        $eid = (int)$this->getQuery('eid', 0);

        $city = $this->getAdminCity();

        $where = ' play_excercise_base.release_status > -1 and play_excercise_base.city = "' . $city . '" ';

        if ($name) {
            $where .= ' and play_excercise_base.name like "%' . $name . '%"';
        }

        if ($bid) {
            //$where['bid'] = $bid;
            $where .= ' and play_excercise_base.id =' . $bid;
        }

        if ($eid) {
            $where .= ' and play_excercise_base.eid =' . $eid;
        }

        if ($start_time and $end_time) {
            $where .= ' and (play_excercise_event.start_time >=' . strtotime($start_time) . ' and play_excercise_event.end_time <=' . (strtotime($end_time) + 3600 * 24) . ')';
        }

        $db = $this->_getAdapter();

        $sql = "SELECT
play_excercise_base.*,
play_excercise_event.start_time,
play_excercise_event.end_time
FROM
play_excercise_base
LEFT JOIN play_excercise_event
ON
play_excercise_base.id = play_excercise_event.bid
WHERE
$where
GROUP BY play_excercise_base.id
ORDER BY
play_excercise_base.id  DESC
LIMIT {$start}, {$pageSum}
";

        $data = $db->query($sql, array())->toArray();

        $sql_count = "SELECT * FROM play_excercise_base left JOIN play_excercise_event
ON play_excercise_base.id = play_excercise_event.bid WHERE $where GROUP BY play_excercise_base.id ";

        $count = $db->query($sql_count, array())->count();

        $url = '/wftadlogin/excercise/index';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $shop = $baseid = $circle = [];
        if ($data) {
            foreach ($data as $k => $d) {
                $baseid[] = $d['id'];
                $bidstr = implode(',', $baseid);
            }
        }

        if (!$baseid) {
            $baseid = $bidstr = 0;
        }

        $shop_id = $this->_getPlayExcerciseShopTable()->getShopList(0, 100, ['bid'], ['bid' => $baseid, 'is_close' => 0])->toArray();

        $my_count = "SELECT bid ,sell_status, COUNT(id) as mynum FROM play_excercise_event GROUP BY bid HAVING bid in (" . $bidstr . ") and sell_status = 2 ";

        $my_counts = $db->query($my_count, array())->toArray();
        $su = [];
        foreach ($my_counts as $mc) {
            $su[$mc['bid']] = $mc['mynum'];
        }


        if ($shop_id) {
            foreach ($shop_id as $s) {
                $shop[$s['bid']] .= $s['shop_name'] . ',<br>';
                $circle[$s['bid']] .= CouponCache::getBusniessCircle($s['busniess_circle'], $s['shop_city']) . ',<br>';
            }
        }

        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
                'shop' => $shop,
                'circle' => $circle,
                'su' => $su,
            )
        );

        return $vm;
    }

    /**
     * 定制活动列表
     */
    public function blistAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $start = ($start < 0) ? 0 : $start;
        $status = $this->getQuery('status', 0);

        $name = $this->getQuery('name', '');
        $start_time = $this->getQuery('start_time', 0);
        $end_time = $this->getQuery('end_time', 0);
        $bid = $this->getQuery('bid', 0);
        $eid = $this->getQuery('eid', 0);
        $city = $this->getAdminCity();

        $where['customize'] = 1;

        if ($name) {
            $where['name'] = $name;
        }

        if ($city) {
            $where['play_excercise_event.city'] = $city;
        }

        if ($status) {
            $where['play_excercise_event.sell_status'] = $status - 2;
        }

        if ($bid) {
            $where['bid'] = $bid;
        }

        if ($eid) {
            $where['eid'] = $eid;
        }

        if ($start_time) {
            $where['play_excercise_event.start_time >= ?'] = strtotime($start_time);
        }

        if ($end_time) {
            $where['play_excercise_event.end_time <= ?'] = strtotime($end_time);
        }

        $data = $this->_getPlayExcerciseEventTable()->getEventList($start, 10, ['*'], $where);
        $count = $this->_getPlayExcerciseEventTable()->fetchCount($where);

        $url = '/wftadlogin/excercise/blist';
        $paginator = new Paginator($page, $count, $pageSum, $url);


        $shop = $circle = [];
        if ($data) {
            $shopid = $price = [];

            foreach ($data as $k => $d) {
                $shopid[] = $d['shop_id'];
                $price[] = $d['id'];
            }
        } else {
            $shopid = $price = $peid = 0;
        }
        if (!$shopid) {
            $shopid = 0;
        }
        $shop_id = $this->_getPlayShopTable()->fetchLimit(0, 10, ['shop_name', 'shop_id', 'busniess_circle'], ['shop_id' => $shopid])->toArray();

        if ($shop_id) {
            foreach ($shop_id as $s) {
                $shop[$s['shop_id']] = $s;
                $circle[$s['shop_id']] = CouponCache::getBusniessCircle($s['busniess_circle']);
            }
        }

        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
                'shop' => $shop,
                'circle' => $circle,
            )
        );

        return $vm;
    }

    /**
     * 定制活动开关
     */
    public function ecloseAction()
    {
        $isclose = (int)$this->getQuery('isclose', 1);
        $id = (int)$this->getQuery('id', 1);

        $status = $this->_getPlayExcerciseEventTable()->update(['sell_status' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->jsonResponsePage(array('status' => $status, 'message' => '操作成功'));
        } else {
            return $this->jsonResponsePage(array('status' => $status, 'message' => '操作失败'));
        }
    }

    public function statusAction()
    {
        $isclose = (int)$this->getQuery('isclose', 1);
        $id = (int)$this->getQuery('id', 1);
        $status = $this->_getPlayExcerciseBaseTable()->update(['release_status' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->_Goto("操作成功");
        } else {
            return $this->_Goto("操作失败");
        }

    }

    public function estatusAction()
    {
        $isclose = (int)$this->getQuery('isclose', 1);


        $update['sell_status'] = $isclose;

        $id = (int)$this->getQuery('id', 1);
        $status = $this->_getPlayExcerciseEventTable()->update($update, ['id' => $id]);

        if ($status) {

            if ($isclose == 3) {
                //结束场次后退订
                $back = new OrderExcerciseBack();
                $db = $this->_getAdapter();
                $res = $db->query("
SELECT
	play_order_info.order_sn,play_order_info.order_city,play_excercise_code.`code`,play_order_info.phone,play_order_info.coupon_name
FROM
	play_excercise_event
LEFT JOIN play_order_info ON play_order_info.coupon_id = play_excercise_event.id
LEFT JOIN play_excercise_code ON play_order_info.order_sn = play_excercise_code.order_sn
WHERE
	play_order_info.order_type = 3
AND play_order_info.order_status = 1
AND play_order_info.pay_status >=2
AND  play_order_info.buy_number >(play_order_info.back_number+play_order_info.backing_number)
AND play_excercise_event.id=?
AND play_excercise_event.sell_status=3
", array($id));
                foreach ($res as $v) {
                    //设为退款中
                    $event_data = $back->backIng($v->order_sn, $v->code, 2);
                }
            }
            $peet = $this->_getPlayExcerciseEventTable()->get(['id' => $id]);
            Kidsplay::updateMax($peet->bid);
            $this->updatelow_money($peet->bid);
            $ec = new ExcerciseCache();
            $vir_num = $ec->getvirnumByBid($peet->bid);
            $this->_getPlayExcerciseBaseTable()->update(['vir_number' => $vir_num], ['id' => $peet->bid]);
            $uc = new UserCache();
            $uc->setVirUser($peet->bid, $vir_num);

            return $this->_Goto("操作成功");
        } else {
            return $this->_Goto("操作失败");
        }

    }

    /**
     * 全部场次
     * @return ViewModel
     */
    public function elistAction()
    {
        //创建分页
        $page = (int)$this->getQuery('p', 1);
        $start_time = $this->getQuery('start_time', 0);
        $end_time = $this->getQuery('end_time', 0);
        $status = $this->getQuery('code_status', 0);
        $bid = (int)$this->getQuery('bid', 0);

        $pageSum = 10;
        $start = ($page - 1) * $pageSum;
        $start = ($start > -1) ? $start : 0;


        if ($status == 3) {
            $where[] = "play_excercise_event.sell_status >= 0
AND play_excercise_event.sell_status >= 1
AND play_excercise_event.open_time < UNIX_TIMESTAMP()
AND over_time > UNIX_TIMESTAMP()
AND (
	play_excercise_event.join_number < play_excercise_event.perfect_number
)";
        } elseif ($status == 4) {
            $where[] = "play_excercise_event.join_number >= play_excercise_event.perfect_number";
        } elseif ($status == 5) {
            $where[] = "over_time < UNIX_TIMESTAMP()";
        } elseif ($status) {
            $where['play_excercise_event.sell_status'] = $status - 2;
        }


        $where['play_excercise_event.customize'] = 0;
        $where['play_excercise_event.bid'] = $bid;


        $where['play_excercise_event.city'] = $this->getAdminCity();


        if ($start_time and $end_time) {
            $where [] = '(play_excercise_event.start_time >=' . strtotime($start_time) . ' and play_excercise_event.end_time <=' . strtotime($end_time) . ')';
        }


        $data = $this->_getPlayExcerciseEventTable()->getEventList($start, $pageSum, ['*'], $where);
        //获取保险天数

        $order_data = $this->_getPlayOrderInfoTable()->get(array('coupon_id' => $data[0]['id']));
        //$produce_code = $this->_getPlayOrderInsureTable()->get(array('order_sn'=>$order_data['order_sn']))->product_code;
        $count = $this->_getPlayExcerciseEventTable()->fetchCount($where);
        $baoyou = new Baoyou();
        foreach ($data as $k => $v) {
            $data[$k]['days'] = $baoyou->getProductInfo($v['product_code'])['DayRange'];
        }
        if ($data) {
            $shopid = $price = [];
            foreach ($data as $d) {
                $shopid[] = $d['shop_id'];
                $price[] = $d['id'];
            }
            $peid = implode(',', $price);
        } else {
            $shopid = $price = $peid = 0;
        }

        $shop_id = $this->_getPlayShopTable()->fetchLimit(0, 10, ['shop_name', 'shop_id'], ['shop_id' => $shopid])->toArray();

        if ($shop_id) {
            $shop = [];
            foreach ($shop_id as $s) {
                $shop[$s['shop_id']] = $s;
            }
        } else {
            $shop = [];
        }

        $url = '/wftadlogin/excercise/elist';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $vm = new viewModel(
            array(
                'data' => $data,
                'pageData' => $paginator->getHtml(),
                'shop' => $shop,
                'start_time' => $start_time,
                'end_time' => $end_time,
            )
        );

        return $vm;
    }


    public function newAction()
    {
        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $p_name = $this->project_name;


        $config_special_labels = $this->_getPlayTagsTable()->fetchAll(array(
            'tag_city' => $this->getCity(),
            'type' => 1
        ), array('sort' => 'desc'))->toArray();

        $vm = new ViewModel(
            array(
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'place' => [],
                'teacher' => $this->teacher,
                'config_special_labels' => $config_special_labels
            )
        );

        return $vm;
    }

    /**
     * 新场次
     */
    public function neweAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $ebt = $this->_getPlayExcerciseBaseTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }

        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        $no = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, ['no'], ['bid' => $id], ['no' => 'desc'])->current();

        if ($no) {
            $data['no'] = $no->no + 1;
        } else {
            $data['no'] = 1;
        }

        $p_name = $this->project_name;
        $vm = new ViewModel(
            array(
                'ebt' => $ebt ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'pst' => $shops,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'bid' => $id,
                'no' => $data['no']
            )
        );
        return $vm;
    }

    /**
     * 定制活动
     */
    public function newcAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $ebt = $this->_getPlayExcerciseBaseTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }

        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        $no = $this->_getPlayExcerciseEventTable()->fetchLimit(0, 1, ['no'], ['bid' => $id], ['no' => 'desc'])->current();

        if ($no) {
            $data['no'] = $no->no + 1;
        } else {
            $data['no'] = 1;
        }

        $p_name = $this->project_name;
        $vm = new ViewModel(
            array(
                'ebt' => $ebt ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'pst' => $shops,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'bid' => $id,
                'no' => $data['no']
            )
        );
        return $vm;
    }

    public function editAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $ebt = $this->_getPlayExcerciseBaseTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }


        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        if ($ebt) {
            $ebt->highlights = htmlspecialchars_decode($ebt->highlights);
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $p_name = $this->project_name;

        $config_special_labels = $config_special_labels = $this->_getPlayTagsTable()->fetchAll(array(
            'tag_city' => $this->getCity(),
            'type' => 1
        ), array('sort' => 'desc'))->toArray();
        $vm = new ViewModel(
            array(
                'ebt' => $ebt ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'pst' => $shops,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'bid' => $id,
                'teacher' => $this->teacher,
                'config_special_labels' => $config_special_labels,
                'special_labels_str'=>implode(',',json_decode($ebt->special_labels,true))
            )
        );
        return $vm;
    }

    public function editeAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $eet = $this->_getPlayExcerciseEventTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 100, [], ['eid' => $id, 'is_close' => array(0,2)])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $eet->bid, 'is_close' => 0])->toArray();

        $bbt = $this->_getPlayExcerciseBaseTable()->get(['id' => $eet->bid]);

        $emt = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }

        // 校验各个收费项是否已有下单，如果有下单则只显示隐藏按钮，并且不能修改。没有下单则可以进行修改与删除操作
        if ($ept) {
            foreach ($ept as $key => $val) {
                if ($this->checkOrderExist($val['id'])) {
                    $ept[$key]['edit_rights'] = true;
                } else {
                    $ept[$key]['edit_rights'] = false;
                }
            }
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        $p_name = $this->project_name;

        $vm = new ViewModel(
            array(
                'eet' => $eet ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'bbt' => $bbt,
                'pst' => $shops,
                'emt' => $emt,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'meeting' => $bbt->meeting,
                'eid' => $id,
            )
        );
        return $vm;
    }

    public function customizeAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $eet = $this->_getPlayExcerciseEventTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 100, [], ['eid' => $id, 'is_close' => 0])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $eet->bid, 'is_close' => 0])->toArray();

        $bbt = $this->_getPlayExcerciseBaseTable()->get(['id' => $eet->bid]);

        $emt = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        $p_name = $this->project_name;

        if ($bbt) {
            //$bbt->highlights = $this->json2html($bbt->highlights);
        }

        $vm = new ViewModel(
            array(
                'eet' => $eet ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'bbt' => $bbt,
                'pst' => $shops,
                'emt' => $emt,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'meeting' => $bbt->meeting,
                'eid' => $id,
            )
        );
        return $vm;
    }

    /**
     * 定制编辑
     * @return ViewModel
     */
    public function editcAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $eet = $this->_getPlayExcerciseEventTable()->get(['id' => $id]);
        $ept = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 100, [], ['eid' => $id, 'is_close' => 0])->toArray();
        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();
        $pst = $this->_getPlayExcerciseShopTable()->fetchLimit(0, 50, [], ['bid' => $eet->bid, 'is_close' => 0])->toArray();

        $bbt = $this->_getPlayExcerciseBaseTable()->get(['id' => $eet->bid]);

        $emt = $this->_getPlayExcerciseMeetingTable()->fetchLimit(0, 50, [], ['eid' => $id, 'is_close' => 0])->toArray();

        $pid = [];
        $checkeds = [];

        if ($pst) {
            foreach ($pst as $p) {
                $pid[] = $p['shopid'];
                $checkeds[] = '"' . $p['shopid'] . '"';
            }
        } else {
            $pid = 0;
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $checked = implode(',', $checkeds);
        $shops = $this->_getPlayShopTable()->fetchLimit(0, 100, ['shop_id', 'shop_name'], ['shop_id' => $pid])->toArray();

        $p_name = $this->project_name;

        $vm = new ViewModel(
            array(
                'eet' => $eet ?: [],
                'ept' => $ept ?: [],
                'est' => $est,
                'bbt' => $bbt,
                'pst' => $shops,
                'emt' => $emt,
                'is_edit' => 1,
                'checked' => $checked,
                'baoyoulist' => $baoyoulist,
                'p_name' => $p_name,
                'meeting' => $bbt->meeting,
                'eid' => $id,
            )
        );
        return $vm;
    }

    //收费项
    public function chargesaveAction()
    {
        $pid = (int)$this->getPost('pid', 0);
        $bid = (int)$this->getPost('bid', 0);
        $eid = (int)$this->getPost('eid', 0);
        $price_name = $this->getPost('price_name', '');
        $price = $this->getPost('price', '');

        $data['price'] = $price;
        $data['price_name'] = $price_name;
        $data['is_other'] = 0;

        if ($pid) {
            $status = $this->_getPlayExcercisePriceTable()->update($data, ['id' => $pid]);
        } else {
            $data['bid'] = $bid;
            $data['eid'] = $eid;
            $status = $this->_getPlayExcercisePriceTable()->insert($data);
            $pid = $this->_getPlayExcercisePriceTable()->getlastInsertValue();
        }
        return $this->jsonResponsePage(array('status' => $status, 'pid' => $pid));
        exit;
    }

    //其它收费项
    public function ochargesaveAction()
    {
        $pid = (int)$this->getPost('pid', 0);
        $bid = (int)$this->getPost('bid', 0);
        $eid = (int)$this->getPost('eid', 0);

        $price_name = $this->getPost('o_price_name', '');
        $price = $this->getPost('o_price', '');


        $data['price'] = $price;
        $data['price_name'] = $price_name;
        $data['is_other'] = 1;

        if ($pid) {
            $status = $this->_getPlayExcercisePriceTable()->update($data, ['id' => $pid]);
        } else {
            $data['bid'] = $bid;
            $data['eid'] = $eid;
            $status = $this->_getPlayExcercisePriceTable()->insert($data);
            $pid = $this->_getPlayExcercisePriceTable()->getlastInsertValue();
        }
        return $this->jsonResponsePage(array('status' => $status, 'pid' => $pid));
        exit;
    }

    //行程安排
    public function rangesaveAction()
    {
        $sid = (int)$this->getPost('sid', 0);
        $bid = (int)$this->getPost('bid', 0);
        $eid = (int)$this->getPost('eid', 0);
        $schedule_name = $this->getPost('schedule_name', '');
        $schedule = $this->getPost('schedule', '');
        $start_day = '1970-01-01';
        $start_time = $this->getPost('start_time');
        $start_time = strtotime($start_day . $start_time);
        $end_day = '1970-01-01';
        $end_time = $this->getPost('end_time');
        $end_time = strtotime($end_day . $end_time);
        $data['schedule_name'] = $schedule_name;
        $data['schedule'] = $schedule;

        $data['start_time'] = $start_time + 3600 * 8;
        $data['end_time'] = $end_time + 3600 * 8;

        if ($sid) {
            $status = $this->_getPlayExcerciseScheduleTable()->update($data, ['id' => $sid]);
        } else {
            $data['bid'] = $bid;
            $data['eid'] = $eid;
            $status = $this->_getPlayExcerciseScheduleTable()->insert($data);
            $sid = $this->_getPlayExcerciseScheduleTable()->getlastInsertValue();
        }
        return $this->jsonResponsePage(array('status' => $status, 'sid' => $sid));
        exit;
    }

    public function saveAction()
    {
        $edata = [];


        // 基本信息
        $edata['name'] = trim($this->getPost('name', ''));                // 活动名称
        $edata['excepted'] = $this->getPost('excepted', 0);//是否使用现金券
        $edata['custom_tags'] = $this->getPost('custom_tags', 0);                // 自定义标签
        $edata['teacher_type'] = (int)$this->getPost('teacher_type', 1);          // 遛娃师类型
        $edata['start_age'] = $this->getPost('start_age', 0);                  // 适合年龄小
        $edata['end_age'] = $this->getPost('end_age', 0);                    // 适合年龄大
        $edata['meeting_desc'] = $this->getPost('meeting_desc', 0);               // 集合说明
        $edata['meeting'] = $this->getPost('meeting', 0);                    // 集合地点
        $edata['phone'] = $this->getPost('phone', '');                     // 咨询电话
        $places = (array)$this->getPost('places', '');             // 游玩地
        $edata['message_custom_content'] = $this->getPost('message_custom_content', '');    // 短信自定义内容
        $edata['full_price'] = $this->getPost('full_price', '');                // 满多少
        $edata['welfare_type'] = $this->getPost('welfare_type', '');              // 参加福利
        $edata['less_price'] = $this->getPost('less_price', '');                // 减多少
        $edata['comment_integral'] = (int)$this->getPost('comment_integral', 0);      // 是否评论奖励积分
        $edata['integral_multiple'] = (int)$this->getPost('integral_multiple', 0);     // 评论积分倍数
        $edata['share_reward'] = (int)$this->getPost('share_reward', '');         // 是否分享奖励
        $special_labels = (array)$this->getPost('special_labels', '');     // 特权标签

        // 活动描述
        $edata['introduction'] = $this->getPost('introduction', '');              // 小玩说
        $edata['attention'] = $this->getPost('attention', '');                 // 注意事项
        $edata['highlights'] = ($this->getPost('highlights', ''));              // 活动亮点

        //行程
        $schedule_name = (array)$this->getPost('schedule_name', '');
        $schedule = (array)$this->getPost('schedule', '');
        $start_time = (array)$this->getPost('start_time');
        $start_day = (array)$this->getPost('start_day');
        // $start_time                      = array_filter($start_time);
        // $start_day                       = array_filter($start_day);
        $end_time = (array)$this->getPost('end_time');
        $end_day = (array)$this->getPost('end_day');
        // $end_time                        = array_filter($end_time);
        // $end_day                         = array_filter($end_day);

        $edata['cover'] = $this->getPost('cover', '');                     // 封面
        $edata['thumb'] = $this->getPost('thumb', '');                     // 缩略图

        // 收费项设置
        // 保险费用
        $edata['insurance_id'] = $this->getPost('insurance_id', '');
        $edata['least_number'] = (int)$this->getPost('least_number', '');
        $edata['perfect_number'] = (int)$this->getPost('perfect_number', '');
        $edata['most_number'] = (int)$this->getPost('most_number', '');
        $edata['vir_ault'] = (int)$this->getPost('vir_ault', '');             // 虚拟票成人
        $edata['vir_child'] = (int)$this->getPost('vir_child', '');            // 虚拟票儿童

        //收费项
        $price_name = (array)$this->getPost('price_name', '');
        $price = (array)$this->getPost('price', '');
        $person = array();                                              // 不再接受参数，而由儿童与成人虚拟票数的总和得出
        $person_ault = (array)$this->getPost('price_item_person_ault', '');  // 出行人数成人
        $person_child = (array)$this->getPost('price_item_person_child', ''); // 出行人数儿童
        $most = (array)$this->getPost('most', '');
        $least = (array)$this->getPost('least', '');
        $single_income = (array)$this->getPost('single_income', '');
        $selectp = (array)$this->getPost('selectp', '');

        //其它项目
        $o_price_name = (array)$this->getPost('o_price_name', '');
        $o_price = (array)$this->getPost('o_price', '');

        $edata['city'] = $this->getAdminCity();                               // 城市

        //各种检查
        if (!$edata['name']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写活动标题！'));
        }

        if ($edata['custom_tags']) {
            $edata['custom_tags'] = implode(',', $edata['custom_tags']);
        }
        if (!$edata['custom_tags']) {
            unset($edata['custom_tags']);
        }

        if (!$edata['meeting_desc']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写集合说明！'));
        }

        $edata['meeting'] = array_filter($edata['meeting']);

        if ($edata['meeting']) {
            $edata['meeting'] = implode(',', $edata['meeting']);
        }
        if (!$edata['meeting']) {
            unset($edata['meeting']);
        }

        if (!$edata['phone']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写咨询电话！'));
        }

        $places = array_filter($places);
        if (!$places) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写游玩地！'));
        }

        if ((!$edata['full_price'] and $edata['full_price'] !== '0' and $edata['full_price'] !== '0.00') || (!$edata['less_price'] and $edata['less_price'] !== '0' and $edata['less_price'] !== '0.00')) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '满多少减多少设置有误！'));
        }

        if (!empty($special_labels)) {
            $edata['special_labels'] = json_encode($special_labels, JSON_UNESCAPED_UNICODE);
        }

        if (!$edata['introduction']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写小玩说！'));
        }

        if (!$edata['attention']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写注意事项！'));
        }

        if (!$edata['highlights']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写活动亮点！'));
        } else {
            // $edata['highlights'] = $this->trmhtml($edata['highlights']);
            //$edata['highlights'] = $edata['highlights'];
        }

        foreach ($schedule_name as $sn) {
            if (!$sn) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程名称有空缺！'));
            }
        }

        foreach ($schedule as $sn) {
            if (!$sn) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程安排有空缺！'));
            }
        }

        foreach ($start_time as $k => $s) {
            if (!(int)$s) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程开始时间设置有误！'));
            }

            $start_time[$k] = strtotime($start_day[$k] . $start_time[$k]);
        }

        if (!$end_time or !$end_day or !$start_time or !$start_day) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '行程时间设置有误！'));
        }

        foreach ($end_time as $k => $s) {
            if (!(int)$s) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程结束时间设置有误！'));
            }
            $end_time[$k] = strtotime($end_day[$k] . $end_time[$k]);

        }

        if (!$start_time || !$end_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置相应时间！'));
        }

        if (!$edata['thumb'] or !$edata['cover']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请上传缩略图或封面图！'));
        }

        if (!$edata['least_number'] or !$edata['perfect_number'] or !$edata['most_number']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写保险与人数中的最少，最佳，最多数量！'));
        }

        $edata['add_dateline'] = time();
        $edata['release_status'] = 0;
        $edata['update_dateline'] = time();

        $price_name = array_filter($price_name);

        foreach ($price_name as $pm) {
            if (!$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项名称！'));
            }
        }

        foreach ($price as $pm) {
            if (!$pm or !(float)$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项单价,或单价设置有误！'));
            }
        }

        foreach ($person_ault as $ps) {
            if (!$ps or !(int)$ps) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行成人数量！'));
            }
        }

        foreach ($person_child as $ps) {
            if (!$ps or !(int)$ps) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行儿童数量！'));
            }
        }


        $o_price_name = array_filter($o_price_name);
        $o_price = array_filter($o_price);
        foreach ($o_price_name as $pm) {
            if (count($o_price_name) and !$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写其它收费项名称！'));
            }
        }

        foreach ($o_price as $pm) {
            if (count($o_price) and !$pm or !(float)$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '其它收费项费用填写有误！'));
            }
        }

        $status = $this->_getPlayExcerciseBaseTable()->insert($edata);

        if ($status) {
            $bid = $this->_getPlayExcerciseBaseTable()->getlastInsertValue();
        } else {
            exit;
        }

        $db = $this->_getAdapter();

        $sql = ' INSERT INTO `play_excercise_schedule`(`bid`,`schedule_name`,`schedule`,`start_time`,`end_time`) VALUES ';
        $val = '';
        foreach ($schedule_name as $k => $v) {
            $val .= '(' . $bid . ',"' . $schedule_name[$k] . '","' . $schedule[$k] . '",' . (int)$start_time[$k] . ',' . (int)$end_time[$k] . '),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;
        $sql = str_replace(';', '', $sql);

        $db->query($sql, array());

        $sql = 'INSERT INTO `play_excercise_price`(`bid`,`price_name`,`price`,`is_other`,`selectp`,`person`,`most`,`least`, `single_income`, `person_ault`, `person_child`) VALUES ';
        $val = '';
        foreach ($selectp as $k => $v) {
            if (!$price[$k]) {
                $price[$k] = 0;
            }
            if ((int)$selectp[$k] !== 99) {
                $price_name[$k] = $this->project_name[$selectp[$k]];
            }
            $person[$k] = $person_ault[$k] + $person_child[$k];
            $val .= '(' . $bid . ',"' . $price_name[$k] . '",' . (float)$price[$k] . ',0,' . $selectp[$k] . ',' . (int)$person[$k] . ',' . (int)$most[$k] . ',' . (int)$least[$k] . ',' . (float)$single_income[$k] . ',' . (int)$person_ault[$k] . ',' . (int)$person_child[$k] . '),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;
        $sql = str_replace(';', '', $sql);
        $db->query($sql, array());

        $sql = 'INSERT INTO `play_excercise_price`(`bid`,`price_name`,`price`,`is_other`) VALUES ';
        $val = '';
        foreach ($o_price_name as $k => $v) {
            if (!$o_price[$k]) {
                $o_price[$k] = 0;
            }
            $val .= '(' . $bid . ',"' . $o_price_name[$k] . '",' . (float)$o_price[$k] . ',1),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;
        $sql = str_replace(';', '', $sql);
        if ($o_price_name) {
            $db->query($sql, array());
        }


        //游玩地
        $sql = ' INSERT INTO `play_excercise_shop`(`bid`,`eid`,`shopid`) VALUES ';
        $val = '';
        foreach ($places as $k => $v) {
            $val .= '(' . $bid . ',0,' . $v . '),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;
        $sql = str_replace(';', '', $sql);
        $db->query($sql, array());

        $this->updatelow_money($bid);

        return $this->jsonResponsePage(array('status' => $bid, 'message' => '添加成功！'));

        exit;
    }

    private function setLowPrice($id)
    {
//        $price = $this->_getPlayExcercisePriceTable()->fetchAll(array('bid' => $id, 'is_other' => 0,'is_close'=>0), array('price' => 'asc'))->toArray();
//        $this->_getPlayExcerciseBaseTable()->update(['low_money' => $price[0]['price']], ['id' => $id]);
        $this->updatelow_money($id);
    }

    /**
     * 保存场次
     */
    public function eventsaveAction()
    {
        $edata = [];

        $edata['bid'] = $this->getPost('bid', 0);//bid

        $pebt = M::getPlayExcerciseBaseTable()->get(['id' => $edata['bid'], 'city' => $this->getAdminCity()]);
        if (!$pebt) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作！'));
        }

        $edata['customize']  = $this->getPost('customize', 0);
        $edata['full_price'] = $this->getPost('full_price', 0);//满多少
        $edata['less_price'] = $this->getPost('less_price', 0);//减多少

        $edata['vir_number'] = (int)$this->getPost('vir_number', 0);//虚拟票
        $edata['excepted']   = $this->getPost('excepted', 0);//是否使用现金券

        $edata['vir_number'] = (int)$this->getPost('vir_number', 0); //虚拟票
        $edata['vir_ault']   = (int)$this->getPost('vir_ault', 0);   //虚拟票成人數量
        $edata['vir_child']  = (int)$this->getPost('vir_child', 0);  //虚拟票儿童数量
        $edata['vir_number'] = $edata['vir_ault'] + $edata['vir_child']; //虚拟票
        $edata['excepted']   = $this->getPost('excepted', 0);//是否使用现金券


        if ((!$edata['full_price'] and $edata['full_price'] !== '0' and $edata['full_price'] !== '0.00') || (!$edata['less_price'] and $edata['less_price'] !== '0' and $edata['less_price'] !== '0.00')) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '满多少减多少设置有误！'));
        }
        $edata['welfare_type'] = $this->getPost('welfare_type', 0);//满人还是满金额,1人0金额
        $edata['share_reward'] = (int)$this->getPost('share_reward', '');//是否奖励
        $edata['meeting_desc'] = $this->getPost('meeting_desc', '');//集合说明

        $edata['teacher_phone'] = $this->getPost('teacher_phone', '4008007221'); //遛娃师电话
        $edata['message_custom_content'] = $this->getPost('message_custom_content', '');  //短信自定义内容

        $edata['add_dateline']    = time(); // 新增时间
        $edata['update_dateline'] = time(); // 修改时间
        $edata['shop_id']         = (int)$this->getPost('shop_id', 0); //shop_id

        $shop = M::getPlayShopTable()->get(['shop_id' => $edata['shop_id']]);
        $edata['shop_name'] = $shop->shop_name;

        $edata['city'] = $this->getAdminCity();//城市

        $edata['start_time'] = strtotime(trim($this->getPost('start_time', '') . $this->getPost('start_timel', '')));
        $edata['end_time'] = strtotime(trim($this->getPost('end_time', '') . $this->getPost('end_timel', '')));
        if (!$edata['start_time'] || !$edata['end_time'] || $edata['end_time'] <= $edata['start_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动开始时间要早于结束时间！'));
        }
        //$edata['meeting_time'] = strtotime(trim($this->getPost('meeting_time', '') . $this->getPost('meeting_time', '')));

        if (!$this->getPost('meeting_time', '')) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置集合地点！'));
        }

        $edata['back_time'] = strtotime(trim($this->getPost('back_time', '') . $this->getPost('back_timel', '')));
        $edata['over_time'] = strtotime(trim($this->getPost('over_time', '') . $this->getPost('over_timel', '')));
        $edata['open_time'] = strtotime(trim($this->getPost('open_time', '') . $this->getPost('open_timel', '')));

        if (!$edata['back_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置最后退款时间！'));
        }

        if (!$edata['over_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置报名截止时间！'));
        }

        if (!$edata['open_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置上架时间！'));
        }

        if ($edata['start_time'] < time()) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动开始时间设置有误，需晚于当前时间！'));
        }

        $edata['insurance_id'] = $this->getPost('insurance_id', '');
        $edata['least_number'] = (int)$this->getPost('least_number', '');
        $edata['perfect_number'] = (int)$this->getPost('perfect_number', '');
        $edata['most_number'] = (int)$this->getPost('most_number', '');
        if (!$edata['least_number'] or !$edata['perfect_number'] or !$edata['most_number']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写保险与人数中的最少，最佳，最多数量！'));
        }
        if (!$edata['start_time'] || !$edata['end_time'] || !$edata['back_time'] || !$edata['over_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置相应时间！'));
        }

        $edata['comment_integral'] = $this->getPost('comment_integral', 0);//是否评论奖励积分
        $edata['integral_multiple'] = (int)$this->getPost('integral_multiple', '');//评论积分倍数

        $start_day = (array)$this->getPost('start_day');
        $start_time = (array)$this->getPost('start_time');
        foreach ($start_day as $k => $s) {
            $start_time[$k] = strtotime($start_day[$k] . $start_time[$k]);
            if (!$start_time[$k]) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '开始时间不要为空！'));
            }
        }
        $end_day = (array)$this->getPost('end_day');
        $end_time = (array)$this->getPost('end_time');
        foreach ($end_day as $k => $s) {
            $end_time[$k] = strtotime($end_day[$k] . $end_time[$k]);
            if (!$end_time[$k]) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '结束时间不要为空！'));
            }
        }

        //集合方式

        $meeting_place = (array)$this->getPost('meeting_place', 0);//集合方式
        $meeting_time = (array)$this->getPost('meeting_time');
        $meeting_timel = (array)$this->getPost('meeting_timel');
        foreach ($meeting_time as $k => $s) {
            if (!$meeting_place[$k]) {
                continue;
            }
            $meeting_time[$k] = strtotime($meeting_time[$k] . $meeting_timel[$k]);
            if (!$meeting_time[$k]) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '集合时间设置有误！'));
            }
        }

        //收费项
        $price_name = (array)$this->getPost('price_name', '');
        $price_name = array_filter($price_name);
        if (!$price_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请至少设置一个收费项！'));
        }

        $price                  = (array)$this->getPost('price', '');
        $person                 = array();
        $person_ault            = (array)$this->getPost('price_item_person_ault', '');
        $person_child           = (array)$this->getPost('price_item_person_child', '');
        $most                   = (array)$this->getPost('most', '');
        $least                  = (array)$this->getPost('least', '');
        $single_income          = (array)$this->getPost('single_income', '');
        $free_coupon_need_count = (array)$this->getPost('free_coupon_need_count', ''); // 兑换一份需要的免费资格券数量
        $free_coupon_max_count  = (array)$this->getPost('free_coupon_max_count', '');  // 可使用免费资格券兑换的份数

        foreach ($price as $p) {
            if (!$p and !(float)$p) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项金额设置有误！'));
            }
        }

        // 暂时屏蔽
        // foreach ($person as $p) {
        //     if (!$p and !(int)$p) {
        //         $this->jsonResponsePage(array('status' => 0, 'message' => '出行人数量设置有误！'));
        //     }
        // }

        foreach ($person_ault as $p) {
            if (!$p and !(int)$p) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '出行成人数量设置有误！'));
            }
        }

        foreach ($person_child as $p) {
            if (!$p and !(int)$p) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '出行儿童数量设置有误！'));
            }
        }


        $selectp = (array)$this->getPost('selectp', '');

        if (count($selectp) !== count($price)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项数据填写有误！'));
        }

        //其它项目
        $o_price_name = (array)$this->getPost('o_price_name', '');
        $o_price = (array)$this->getPost('o_price', '');
        $o_price = array_filter($o_price);
        foreach ($o_price as $p) {
            if (!($p) and !(float)($p)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '其它收费项金额设置有误！'));
            }
        }

        $no = M::getPlayExcerciseEventTable()->fetchLimit(0, 1, ['no'], ['bid' => $edata['bid']], ['no' => 'desc'])->current();
        if ($no) {
            $edata['no'] = $no->no + 1;
        } else {
            $edata['no'] = 1;
        }

        $status = M::getPlayExcerciseEventTable()->insert($edata);
        if ($status) {
            $eid = M::getPlayExcerciseEventTable()->getlastInsertValue();
        } else {
            exit;
        }
        $db = $this->_getAdapter();

        $sql = 'INSERT INTO `play_excercise_price`(`eid`,`price_name`,`price`,`is_other`,`selectp`,`person`,`most`,`least`, `single_income`, `person_ault`, `person_child`, `free_coupon_need_count`, `free_coupon_max_count`) VALUES ';
        $val = '';

        $data_free_memberindex = false;
        foreach ($selectp as $k => $v) {
            if ((int)$selectp[$k] !== 99) {
                $price_name[$k] = $this->project_name[$selectp[$k]];
            }
            //$val .= '(' . $eid . ',"' . $price_name[$k] . '",' . $price[$k] . ',0,' . $selectp[$k] . '),';
            $person[$k] = (int)$person_ault[$k] + (int)$person_child[$k];
            $val .= '(' . $eid . ',"' . $price_name[$k] . '",' . (float)$price[$k] . ',0,' . $selectp[$k] . ',' . (int)$person[$k] . ',' . (int)$most[$k] . ',' . (int)$least[$k] . ',' . (float)$single_income[$k] . ',' . (int)$person_ault[$k] . ',' . (int)$person_child[$k] . ',' . (int)$free_coupon_need_count[$k] . ',' . (int)$free_coupon_max_count[$k] . '),';

            if ((int)$free_coupon_need_count[$k] > 0) {
                $data_free_memberindex = true;
            }
        }

        if ($data_free_memberindex) {
            $data_kidsplay_base = Kidsplay::getKidsplayBaseById ($edata['bid']);

            $data_param_status = array(
                'status'     => 1,
                'block_title'=> $data_kidsplay_base['name'],
                'link_img'   => $data_kidsplay_base['cover'],
                'dateline'   => time(),
            );

            $data_param_where= array(
                'link_id'    => $edata['bid'],
                'type'       => 16,
                'block_city' => $this->getAdminCity(),
                'link_type'  => 9,
            );

            IndexBlock::setIndexBlock($data_param_status, $data_param_where);
        }

        $val = rtrim($val, ',');
        $sql .= $val;
        $db->query($sql, array());

        //其它收费可以没有
        $o_price_name = array_filter($o_price_name);
        if ($o_price_name) {
            $sql = 'INSERT INTO `play_excercise_price`(`eid`,`price_name`,`price`,`is_other`) VALUES ';
            $val = '';
            foreach ($o_price_name as $k => $v) {
                $val .= '(' . $eid . ',"' . $o_price_name[$k] . '",' . $o_price[$k] . ',1),';
            }
            $val = rtrim($val, ',');
            $sql .= $val;
            $db->query($sql, array());
        }

        $places = (array)$this->getPost('shop_id', '');

        $sql = ' INSERT INTO `play_excercise_shop`(`bid`,`eid`,`shopid`) VALUES ';
        $val = '';
        foreach ($places as $k => $v) {
            $val .= '(0,' . $eid . ',' . $v . '),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;

        $db->query($sql, array());


        $sql = ' INSERT INTO `play_excercise_meeting`(`meeting_place`,`meeting_time`,`eid`,`is_close`) VALUES ';
        $val = '';
        foreach ($meeting_time as $k => $v) {
            $val .= '("' . $meeting_place[$k] . '",' . (int)$v . ',' . $eid . ',0),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;

        $db->query($sql, array());

        $service_kisplay              = new Kidsplay();
        $vir_num                      = $service_kisplay->getvirnumByBid($edata['bid']);
        $vir_all_ault                 = $service_kisplay->getviraultByBid($edata['bid']);
        $vir_all_child                = $service_kisplay->getvirchildByBid($edata['bid']);
        $data_free_coupon_event_count = $service_kisplay->getFreeCouponEventCount($edata['bid']);
        $data_free_coupon_event_max   = $service_kisplay->getFreeCouponEventMax($edata['bid']);

        M::getPlayExcerciseBaseTable()->update(
            array(
                'vir_number'              => $vir_num,
                'vir_ault'                => $vir_all_ault,
                'vir_child'               => $vir_all_child,
                'free_coupon_event_count' => $data_free_coupon_event_count,
                'free_coupon_event_max'   => $data_free_coupon_event_max,
                'all_number' => new Expression('all_number + 1')
            ),
            array(
                'id' => $edata['bid']
            )
        );

        $uc = new UserCache();
        $uc->setVirUser($edata['bid'], $vir_num);
        KidsPlay::updateMax($edata['bid']);

        $this->updatelow_money($edata['bid']);

        return $this->jsonResponsePage(array('status' => 1, 'message' => '添加成功！'));

        exit;
    }

    public function baseswitchAction()
    {

    }

    public function eventswitchAction()
    {

    }

    public function scheduledelAction()
    {
        $id = (int)$this->getPost('id', 0);
        $isclose = (int)$this->getPost('is_close', 1);
        if (!$id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $est = $this->_getPlayExcerciseScheduleTable()->fetchLimit(0, 1, [], ['id' => $id])->current();

        if (!$est) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $all = $this->_getPlayExcerciseScheduleTable()->fetchCount(['bid' => $est->bid, 'is_close' => 0]);
        if ($all < 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请保留一个行程！'));
            exit;
        }

        $status = $this->_getPlayExcerciseScheduleTable()->update(['is_close' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功！'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '操作失败！'));
        }
        exit;
    }

    public function epricedelAction()
    {
        $id = (int)$this->getPost('id', 0);
        $isclose = (int)$this->getPost('is_close', 1);
        if (!$id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $price = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 1, [], ['id' => $id])->current();
        if (!$price) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $all = $this->_getPlayExcercisePriceTable()->fetchCount(['eid' => $price->eid, 'is_other' => 0, 'is_close' => 0]);

        if ($price->is_other == 0 and $all < 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请保留一个收费项！'));
            exit;
        }


        $status = $this->_getPlayExcercisePriceTable()->update(['is_close' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功！'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '操作失败！'));
        }
        exit;
    }

    public function epriceshowAction()
    {
        $id = (int)$this->getPost('id', 0);
        $isclose = (int)$this->getPost('is_close', 0);
        if (!$id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $price = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 1, [], ['id' => $id])->current();
        if (!$price) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $status = $this->_getPlayExcercisePriceTable()->update(['is_close' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功！'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '操作失败！'));
        }
        exit;
    }

    public function pricedelAction()
    {
        $id = (int)$this->getPost('id', 0);
        $isclose = (int)$this->getPost('is_close', 1);
        if (!$id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $price = $this->_getPlayExcercisePriceTable()->fetchLimit(0, 1, [], ['id' => $id])->current();
        if (!$price) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误！'));
        }

        $all = $this->_getPlayExcercisePriceTable()->fetchCount(['bid' => $price->eid, 'is_other' => 0, 'is_close' => 0]);

        if ($price->is_other == 0 and $all < 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请保留一个收费项！'));
            exit;
        }


        $status = $this->_getPlayExcercisePriceTable()->update(['is_close' => $isclose], ['id' => $id]);

        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功！'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '操作失败！'));
        }
        exit;
    }

    public function updateAction()
    {

        $bid = (int)$this->getPost('id', 0);
        $edata = [];

        $pebt = $this->_getPlayExcerciseBaseTable()->get(['id' => $bid, 'city' => $this->getAdminCity()]);
        if (!$pebt) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作！'));
        }


        $edata['name'] = trim($this->getPost('name', ''));
        $edata['excepted'] = $this->getPost('excepted', 0);//是否使用现金券
        $edata['custom_tags'] = $this->getPost('custom_tags', 0);           // 集合方式
        if ($edata['custom_tags']) {
            $edata['custom_tags'] = array_filter($edata['custom_tags']);
            $edata['custom_tags'] = implode(',', $edata['custom_tags']);
        } else {
            $edata['custom_tags'] = '';
        }

        $edata['teacher_type'] = (int)$this->getPost('teacher_type', 1);    // 遛娃师类型
        $edata['start_age'] = $this->getPost('start_age', 0);            // 适合年龄小
        $edata['end_age'] = $this->getPost('end_age', 0);              // 适合年龄大
        $edata['meeting_desc'] = $this->getPost('meeting_desc', 0);         // 集合说明
        $edata['meeting'] = $this->getPost('meeting', 0);              // 集合方式
        if ($edata['meeting']) {
            $edata['meeting'] = array_filter($edata['meeting']);
            $edata['meeting'] = implode(',', $edata['meeting']);
        } else {
            unset($edata['meeting']);
        }

        $edata['phone'] = $this->getPost('phone', '');                  // 咨询电话
        $places = (array)$this->getPost('places', '');          // 游玩地
        $places = array_filter($places);
        $edata['message_custom_content'] = $this->getPost('message_custom_content', '');  // 短信自定义内容
        $edata['full_price'] = $this->getPost('full_price', '');             // 满多少
        $edata['less_price'] = $this->getPost('less_price', '');             // 减多少
        $edata['welfare_type'] = $this->getPost('welfare_type', 0);            // 满人还是满金额,1人0金额
        $edata['comment_integral'] = $this->getPost('comment_integral', 0);        // 是否评论奖励积分
        $edata['integral_multiple'] = (int)$this->getPost('integral_multiple', ''); // 评论积分倍数
        $edata['share_reward'] = (int)$this->getPost('share_reward', '');      // 是否奖励
        $special_labels = (array)$this->getPost('special_labels', '');     // 特权标签

        $edata['introduction'] = $this->getPost('introduction', '');           // 小玩说
        $edata['attention'] = $this->getPost('attention', '');              // 注意事项
        $edata['highlights'] = $this->getPost('editorValue', '');            // 活动亮点

        // 行程安排
        $start_time = (array)$this->getPost('start_time', '');
        $end_time = (array)$this->getPost('end_time', '');
        $start_day = (array)$this->getPost('start_day', '');
        $end_day = (array)$this->getPost('end_day', '');
//        $start_time = array_filter($start_time);
//        $start_day = array_filter($start_day);
        $schedule = (array)$this->getPost('schedule', '');
        $schedule_name = (array)$this->getPost('schedule_name', '下一天的行程');
        $sid = (array)$this->getPost('sid', 0);
        $schedule = array_filter($schedule);
        $schedule_name = array_filter($schedule_name);

        $edata['thumb'] = $this->getPost('thumb', '');                  // 缩略面
        $edata['cover'] = $this->getPost('cover', '');                  // 封面面

        // 保险与人数
        $edata['insurance_id'] = $this->getPost('insurance_id', '');
        $edata['least_number'] = $this->getPost('least_number', '');
        $edata['perfect_number'] = $this->getPost('perfect_number', '');
        $edata['most_number'] = $this->getPost('most_number', '');
        $edata['vir_ault'] = (int)$this->getPost('vir_ault', '');          // 虚拟票成人
        $edata['vir_child'] = (int)$this->getPost('vir_child', '');         // 虚拟票儿童

        //处理收费项
        $price_name = (array)$this->getPost('price_name', '');
        $price_name = array_filter($price_name);
        $price = (array)$this->getPost('price', '');
        $price = array_filter($price);
        $person = array();
        $person_ault = (array)$this->getPost('price_item_person_ault', '');  // 出行成人人数
        $person_child = (array)$this->getPost('price_item_person_child', ''); // 出行儿童人数
        $most = (array)$this->getPost('most', '');
        $least = (array)$this->getPost('least', '');
        $single_income = (array)$this->getPost('single_income', '');
        $pid = (array)$this->getPost('pid', 0);
        $selectp = (array)$this->getPost('selectp', 0);

        // 其他收费项
        $o_price_name = (array)$this->getPost('o_price_name', '');
        $o_price = (array)$this->getPost('o_price', '');
        $o_price_name = array_filter($o_price_name);
        $o_price = array_filter($o_price);

        $edata['contact'] = $this->getPost('contact', 0);//是否需要联系方式

        $edata['update_dateline'] = time();  // 更新时间
        //各种检查
        if (!$edata['name']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写活动标题！'));
        }

        if (!$edata['meeting_desc']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写集合说明！'));
        }


        if (!$edata['phone']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写咨询电话！'));
        }

        if (!$places) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请勾选游玩地！'));
        }

        if ((!$edata['full_price'] and $edata['full_price'] !== '0' and $edata['full_price'] !== '0.00') || (!$edata['less_price'] and $edata['less_price'] !== '0' and $edata['less_price'] !== '0.00')) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '满多少减多少设置有误！'));
        }

        if (!empty($special_labels)) {
            $edata['special_labels'] = json_encode($special_labels, JSON_UNESCAPED_UNICODE);
        }

        if (!$edata['introduction']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写小玩说！'));
        }

        if (!$edata['attention']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写注意事项！'));
        }

        if (!$edata['highlights']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写活动亮点！'));
        } else {
            //$edata['highlights'] = $this->trmhtml($edata['highlights']);
            //$edata['highlights'] = $edata['highlights'];
        }

        if (!$end_time or !$end_day or !$start_time or !$start_day) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '行程时间设置有误！'));
        }

        foreach ($end_time as $k => $s) {
            if (!(int)$s) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程结束时间设置有误！'));
            }

            $end_time[$k] = strtotime($end_day[$k] . $end_time[$k]);

        }

        foreach ($start_time as $k => $s) {
            if (!(int)$s) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程开始时间设置有误！'));
            }
            $start_time[$k] = strtotime($start_day[$k] . $start_time[$k]);
        }

        if (count($schedule) !== count($end_day)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '行程数据不对！'));
        }

        foreach ($schedule_name as $sn) {
            if (!$sn) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程名称有空缺！'));
            }
        }

        foreach ($schedule as $sn) {
            if (!$sn) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '行程安排有空缺！'));
            }
        }

        if (!$edata['thumb'] || !$edata['cover']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '封面或者缩图不存在！'));
        }

        if (!$edata['least_number'] or !$edata['perfect_number'] or !$edata['most_number']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写保险与人数中的最少，最佳，最多数量！'));
        }

        foreach ($price_name as $pm) {
            if (!$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项名称！'));
            }
        }

        foreach ($price as $p) {
            if (!$p or !(float)$p) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项金额设置有误！'));
            }
        }

        // 暂时屏蔽
        // foreach ($person as $ps) {
        //     if (!$ps or !(int)$ps) {
        //         $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行人数！'));
        //     }
        // }

        foreach ($person_ault as $ps) {
            if (!$ps or !(int)$ps) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行成人人数！'));
            }
        }

        foreach ($person_child as $ps) {
            if (!$ps or !(int)$ps) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行儿童人数！'));
            }
        }


        if (count($selectp) !== count($price)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项数据不对！'));
        }


        foreach ($o_price_name as $pm) {
            if (count($o_price_name) and !$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写其它收费项名称！'));
            }
        }

        foreach ($o_price as $pm) {
            if (count($o_price) and !$pm) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '其它收费项费用填写有误！'));
            }
        }

        if ($bid) {
            $this->_getPlayExcerciseBaseTable()->update($edata, ['id' => $bid]);
        }

        //处理行程
        $this->_getPlayExcerciseScheduleTable()->update(['is_close' => 1], ['bid' => $bid, 'id > ?' => 0]);

        $db = $this->_getAdapter();
        $sql = 'UPDATE `play_excercise_shop` set is_close = 1 WHERE bid = ? and id > 0 ';
        $db->query($sql, array($bid));


        $sql = ' INSERT INTO `play_excercise_shop`(`bid`,`eid`,`shopid`) VALUES ';
        $val = '';
        foreach ($places as $k => $v) {
            $val .= '(' . $bid . ',0,' . $v . '),';
        }
        $val = rtrim($val, ',');
        $sql .= $val;
        $db->query($sql, array());


        $sche_data = [];
        foreach ($start_time as $k => $v) {


            $sche_data['start_time'] = (int)$start_time[$k];
            $sche_data['end_time'] = (int)$end_time[$k];

            $sche_data['schedule'] = $schedule[$k];
            $sche_data['schedule_name'] = $schedule_name[$k];
            $sche_data['is_close'] = 0;
            $scid = (int)$sid[$k];
            if ($scid) {
                $this->_getPlayExcerciseScheduleTable()->update($sche_data, ['bid' => $bid, 'id' => $scid]);
            } else {
                $sche_data['bid'] = $bid;
                $this->_getPlayExcerciseScheduleTable()->insert($sche_data);
            }
        }

        $this->_getPlayExcercisePriceTable()->update(['is_close' => 1], ['bid' => $bid, 'id > ?' => 0]);

        $price_data = [];
        foreach ($selectp as $k => $p) {
            $price_data['selectp'] = $selectp[$k];
            if ((int)$selectp[$k] !== 99) {
                $price_name[$k] = $this->project_name[$selectp[$k]];
            }
            $price_data['price_name'] = $price_name[$k];
            $price_data['price'] = (float)$price[$k];
            $price_data['person'] = (int)$person_ault[$k] + (int)$person_child[$k];
            $price_data['person_ault'] = (int)$person_ault[$k];
            $price_data['person_child'] = (int)$person_child[$k];
            $price_data['most'] = (int)$most[$k];
            $price_data['least'] = (int)$least[$k];
            $price_data['single_income'] = (float)$single_income[$k];
            $price_data['is_close'] = 0;

            $ppid = (int)$pid[$k];
            if ($ppid) {
                $this->_getPlayExcercisePriceTable()->update($price_data, ['bid' => $bid, 'id' => $ppid]);
            } else {
                $price_data['bid'] = $bid;
                $price_data['is_other'] = 0;
                $price_data['eid'] = 0;
                $this->_getPlayExcercisePriceTable()->insert($price_data);
            }
        }


        //处理其它收费项
        $pid = (array)$this->getPost('o_pid', 0);
        $price_data = [];

        $price_name = array_filter($o_price_name);

        foreach ($price_name as $k => $p) {
            $price_data['price_name'] = $price_name[$k];
            $price_data['price'] = (float)$o_price[$k];
            //$price_data['buy_number'] = $buy_number[$k];
            $price_data['is_close'] = 0;

            $ppid = (int)$pid[$k];
            if ($ppid) {
                $this->_getPlayExcercisePriceTable()->update($price_data, ['bid' => $bid, 'id' => $ppid]);
            } else {
                $price_data['bid'] = $bid;
                $price_data['eid'] = 0;
                $price_data['is_other'] = 1;
                $this->_getPlayExcercisePriceTable()->insert($price_data);
            }
        }
        $this->updatelow_money($bid);
        return $this->jsonResponsePage(array('status' => 1, 'message' => '更新成功！'));
        exit;
    }

    /**
     * 场次更新
     */
    public function eventupdateAction() {
        $eid = (int)$this->getPost('id', 0);
        $edata = [];

        $pebt = M::getPlayExcerciseEventTable()->get(array(
            'id'   => $eid,
            'city' => $this->getAdminCity(),
        ));

        if (!$pebt) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作！'));
        }

        $edata['shop_id'] = trim($this->getPost('shop_id', 0));
        $shop             = M::getPlayShopTable()->get(array(
            'shop_id' => $edata['shop_id'],
        ));
        $edata['shop_name']    = $shop->shop_name;

        $edata['insurance_id'] = $this->getPost('insurance_id', '');
        $edata['excepted']     = $this->getPost('excepted', 0);//是否使用现金券
        $edata['vir_number']   = (int)$this->getPost('vir_number', 0); //虚拟票
        $edata['vir_ault']     = (int)$this->getPost('vir_ault', 0);   //虚拟票成人数量
        $edata['vir_child']    = (int)$this->getPost('vir_child', 0);  //虚拟票儿童数量
        $edata['vir_number']   = $edata['vir_ault'] + $edata['vir_child'];

        $edata['least_number']   = $this->getPost('least_number', '');
        $edata['perfect_number'] = $this->getPost('perfect_number', '');
        $edata['most_number']    = $this->getPost('most_number', '');

        if (!$edata['least_number'] or !$edata['perfect_number'] or !$edata['most_number']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的最少，完美，最多数量！'));
        }

        $edata['welfare_type']    = $this->getPost('welfare_type', '');//参加福利

        $edata['full_price']      = (float)$this->getPost('full_price', '');//满多少
        $edata['less_price']      = (float)$this->getPost('less_price', '');//减多少

        $edata['share_reward']    = (int)$this->getPost('share_reward', '');//是否奖励

        $edata['update_dateline'] = time();//减多少

        $edata['comment_integral']  = $this->getPost('comment_integral', 0);//是否评论奖励积分
        $edata['integral_multiple'] = (int)$this->getPost('integral_multiple', '');//评论积分倍数

        $edata['teacher_phone']          = $this->getPost('teacher_phone', '4008007221'); //遛娃师电话
        $edata['message_custom_content'] = $this->getPost('message_custom_content', '');  //短信自定义内容

        $edata['start_time'] = (int)strtotime(trim($this->getPost('start_time', '') . $this->getPost('start_timel', '')));
        $edata['end_time']   = (int)strtotime(trim($this->getPost('end_time', '')   . $this->getPost('end_timel',   '')));
        $edata['back_time']  = (int)strtotime(trim($this->getPost('back_time', '')  . $this->getPost('back_timel',  '')));
        $edata['over_time']  = (int)strtotime(trim($this->getPost('over_time', '')  . $this->getPost('over_timel',  '')));
        $edata['open_time']  = (int)strtotime(trim($this->getPost('open_time', '')  . $this->getPost('open_timel',  '')));

        if (!$edata['start_time'] || !$edata['end_time'] || $edata['end_time'] <= $edata['start_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动开始时间要早于结束时间！'));
        }

        if (!$edata['back_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置最后退款时间！'));
        }

        if (!$edata['over_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置报名截止时间！'));
        }

        if (!$edata['open_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置上架时间！'));
        }


        if ($edata['start_time'] >= $edata['end_time']) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '活动开始时间要早于结束时间！'));
        }

        $edata['meeting_desc'] = $this->getPost('meeting_desc', 0);//集合说明


        if (!$this->getPost('meeting_time', '')) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请设置集合地点！'));
        }

        //处理收费项
        $price_name             = (array)$this->getPost('price_name', '');
        $price                  = (array)$this->getPost('price', '');
        $person                 = (array)$this->getPost('person', '');
        $person_ault            = (array)$this->getPost('price_item_person_ault', '');  // 出行人数成人数量
        $person_child           = (array)$this->getPost('price_item_person_child', ''); // 出行人数儿童数量
        $most                   = (array)$this->getPost('most', '');
        $least                  = (array)$this->getPost('least', '');
        $single_income          = (array)$this->getPost('single_income', '');
        $selectp                = (array)$this->getPost('selectp', '');
        $free_coupon_need_count = (array)$this->getPost('free_coupon_need_count', ''); // 兑换一份需要的免费资格券数量
        $free_coupon_max_count  = (array)$this->getPost('free_coupon_max_count', '');  // 可使用免费资格券兑换的份数

        foreach ($price as $pr) {
            if (!$pr or !(float)$pr) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项价格不可以为空！'));
            }
        }
        foreach ($person as $ps) {
            if (!$ps or !(int)$ps) {
                $this->jsonResponsePage(array('status' => 0, 'message' => '请填写收费项中的出行人数！'));
            }
        }
        $price_name = array_filter($price_name);
        if (count($selectp) !== count($price)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '收费项填写有误！'));
        }

        if ($eid) {
            M::getPlayExcerciseEventTable()->update($edata, array(
                'id' => $eid
            ));
        }

//        M::getPlayExcercisePriceTable()->update(array(
//            'is_close' => 1
//        ), array(
//            'eid' => $eid,
//            'id > ?' => 0
//        ));

        $pid = (array)$this->getPost('pid', 0);

        $price_data = [];
        $data_free_memberindex = false;
        foreach ($selectp as $k => $p) {
            $price_data['selectp'] = $selectp[$k];
            if ((int)$selectp[$k] !== 99) {
                $price_name[$k] = $this->project_name[$selectp[$k]];
            }
            $price_data['price_name']             = $price_name[$k];
            $price_data['price']                  = (float)$price[$k];
            $price_data['person_ault']            = (int)$person_ault[$k];
            $price_data['person_child']           = (int)$person_child[$k];
            $price_data['person']                 = (int)$person_ault[$k] + (int)$person_child[$k];
            $price_data['most']                   = (int)$most[$k];
            $price_data['least']                  = (int)$least[$k];
            $price_data['single_income']          = (float)$single_income[$k];
            $price_data['free_coupon_need_count'] = (int)$free_coupon_need_count[$k];
            $price_data['free_coupon_max_count']  = (int)$free_coupon_max_count[$k];

            $ppid = (int)$pid[$k];

            if ($ppid) {
                M::getPlayExcercisePriceTable()->update($price_data, array(
                    'eid' => $eid,
                    'id'  => $ppid,
                ));
                Logger::writeLog('临时监控：' . __DIR__ . print_r($price_data,1));
            } else {
                $price_data['bid'] = 0;
                $price_data['is_other'] = 0;
                $price_data['eid'] = $eid;
                M::getPlayExcercisePriceTable()->insert($price_data);
            }

            if ((int)$free_coupon_need_count[$k] > 0) {
                $data_free_memberindex = true;
            }
        }

        //处理其它收费项

        $price_name = (array)$this->getPost('o_price_name', '');
        $price      = (array)$this->getPost('o_price', '');
        $pid        = (array)$this->getPost('o_pid', 0);
        $price_name = array_filter($price_name);
        $price_data = [];
        foreach ($price_name as $k => $p) {
            $price_data['price_name'] = $price_name[$k];
            $price_data['price']      = $price[$k];
            $price_data['is_close']   = 0;

            $ppid = (int)$pid[$k];
            if ($ppid) {
                M::getPlayExcercisePriceTable()->update($price_data, array(
                    'eid' => $eid,
                    'id'  => $ppid
                ));
            } else {
                $price_data['bid'] = 0;
                $price_data['eid'] = $eid;
                $price_data['is_other'] = 1;
                M::getPlayExcercisePriceTable()->insert($price_data);
            }
        }

        if ($data_free_memberindex) {
            $data_kidsplay_base = Kidsplay::getKidsplayBaseById ($pebt->bid);

            $data_param_status = array(
                'status'     => 1,
                'block_title'=> $data_kidsplay_base['name'],
                'link_img'   => $data_kidsplay_base['cover'],
                'dateline'   => time(),
            );

            $data_param_where= array(
                'link_id'    => $pebt->bid,
                'type'       => 16,
                'block_city' => $this->getAdminCity(),
                'link_type'  => 9,
            );

            IndexBlock::setIndexBlock($data_param_status, $data_param_where);
        }

        //集合方式
        $meeting_place = (array)$this->getPost('meeting_place'); // 集合方式
        $meeting_time  = (array)$this->getPost('meeting_time');
        $meeting_timel = (array)$this->getPost('meeting_timel');

        foreach ($meeting_time as $k => $s) {
            $meeting_time[$k] = strtotime($meeting_time[$k] . $meeting_timel[$k]);
        }

        M::getPlayExcerciseMeetingTable()->update(['is_close' => 1], ['eid' => $eid, 'id > ?' => 0]);
        $mids = (array)$this->getPost('mid', 0);
        foreach ($meeting_time as $k => $v) {
            if (!$meeting_place[$k]) {
                continue;
            }
            if (!$v) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '集合时间设置有误！'));
            }
            $meeting_data['meeting_place'] = $meeting_place[$k];
            $meeting_data['meeting_time'] = (int)$v;
            $meeting_data['is_close'] = 0;

            $mid = (int)$mids[$k];
            if ($mid) {
                M::getPlayExcerciseMeetingTable()->update($meeting_data, ['eid' => $eid, 'id' => $mid]);
            } else {
                $meeting_data['eid'] = $eid;
                $meeting_data['is_close'] = 0;
                M::getPlayExcerciseMeetingTable()->insert($meeting_data);
            }
        }

        $service_kisplay              = new Kidsplay();
        $vir_num                      = $service_kisplay->getvirnumByBid($pebt->bid);
        $vir_all_ault                 = $service_kisplay->getviraultByBid($pebt->bid);
        $vir_all_child                = $service_kisplay->getvirchildByBid($pebt->bid);
        $data_free_coupon_event_count = $service_kisplay->getFreeCouponEventCount($pebt->bid);
        $data_free_coupon_event_max   = $service_kisplay->getFreeCouponEventMax($pebt->bid);

        M::getPlayExcerciseBaseTable()->update(array(
            'vir_number'              => $vir_num,
            'vir_ault'                => $vir_all_ault,
            'vir_child'               => $vir_all_child,
            'free_coupon_event_count' => $data_free_coupon_event_count,
            'free_coupon_event_max'   => $data_free_coupon_event_max,
        ), array(
            'id' => $pebt->bid,
        ));

        //虚拟头像
        $uc = new UserCache();
        $uc->setVirUser($pebt->bid, $vir_num);

        KidsPlay::updateMax($pebt->bid);
        $this->updatelow_money($pebt->bid);

        return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功！'));
    }


    //人员名单
    public function personlistAction()
    {
        $id = (int)$this->getQuery("id", 0);//场次id
        $bid = (int)$this->getQuery("bid", 0);//活动id
        $page = (int)$this->getQuery('p', 1);
        $pageNum = (int)$this->getQuery("pageNum", 10);
        $start = ($page - 1) * $pageNum;


        if (!$id and !$bid) {
            return $this->_Goto("参数错误");
        }

        $name = trim($this->getQuery("user_name", null));
        $uid = (int)$this->getQuery("user_id", 0);
        $order_sn = trim($this->getQuery("order_sn", null));
        $order_sn = str_replace('WFT', '', $order_sn);
        $phone = trim($this->getQuery("phone", null));
        $traveller = trim($this->getQuery("traveller", null));
//        $buy_time = trim($this->getQuery('buy_time', null));

        $buy_start = trim($this->getQuery('buy_start', null));
        $buy_end = trim($this->getQuery('buy_end', null));

        $policy_status = (int)$this->getQuery("status", 0);
        $out = $this->getQuery('out');
        //活动数据
        $excerciseData = $this->_getPlayExcerciseEventTable()->getEventInfo(array("play_excercise_event.id={$id} or play_excercise_event.bid={$bid} ", 'play_excercise_event.city' => $this->getAdminCity()));


        if ($bid) {
            $where = "play_order_otherdata.full_sssociates = 1 and play_order_info.pay_status >= 2 ";
        } elseif ($id) {
            $where = "play_order_insure.coupon_id={$id} and play_order_info.pay_status >= 2 ";
        }
        $where .= " and play_order_info.order_status=1 ";

        if ($name) {
            $where .= " and play_order_info.username='{$name}'";
        }

        if ($uid) {
            $where .= " and play_order_info.user_id = {$uid}";
        }

        if ($order_sn) {
            $where .= " and play_order_insure.order_sn='{$order_sn}'";
        }

        if ($phone) {
            $where .= " and play_order_info.phone='{$phone}'";
        }

        if ($traveller) {
            $where .= " and play_order_insure.name like '%{$traveller}%'";
        }

//        if ($buy_time) {
//            $where .= " and play_order_info.dateline >= {$buy_time}";
//        }

        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where . " AND play_order_info.dateline >= {$buy_start}";
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 3600 * 24;
            $where = $where . " AND play_order_info.dateline <= {$buy_end}";
        }


        if ($policy_status) {
            if ($policy_status == 1) {//未投保
                $where .= " and play_order_insure.insure_status<=1 ";
            }
            if ($policy_status == 2) {//投保失败
                $where .= " and play_order_insure.insure_status=4";
            }
            if ($policy_status == 3) {//已投保
                $where .= " and play_order_insure.insure_status=3";
            }
        }

        $data = $this->_getPlayOrderInsureTable()->getPersonList($start, $pageNum, $where);


        $count = $this->_getPlayOrderInsureTable()->getPersonList($start, 0, $where)->count();
        //创建分页
        $url = '/wftadlogin/excercise/personlist';
        $paginator = new Paginator($page, $count, $pageNum, $url);

        //导出
        if ($out) {
            //$excerciseData
            $data = $this->_getPlayOrderInsureTable()->outPersonList($where);

            $out = new OutPut();
            $file_name = date('Y-m-d H:i:s', time()) . '_保险人员名单列表.csv';
            $row1 = [$excerciseData->name . ' 第' . $excerciseData->no . '期', '', '', '', '', '', '', '', '', '', ''];
//            $row2 = [date('Y-m-d H:i:s', $excerciseData->start_time) . ' 活动名单', '', '', '', '', '', '', '', '', '', ''];
            $xh = 1;
            $head = array(
                '序号',
                '活动订单号',
                '下单人用户ID',
                '用户名',
                '手机号',
                '联系电话',
                '购买套系',
                '姓名',
                '身份证号码',
                '年龄',
                '性别',
                '游玩地',
                '集合地点',
                '备注',
                //'类别',

            );
            $info = array();
            foreach ($data as $v) {
                $age = round(date('Ymd') / 10000 - $v['birth'] / 10000);
                if ($v['id_num'] and $age > 17 and $age < 99) {
                    $type = '成人';
                } elseif ($v['id_num'] and $age < 18) {
                    $type = '儿童';
                } else {
                    $type = '';
                }

                $info[] = array(
                    $xh++,
                    'WFT' . $v['order_sn'],
                    $v['user_id'],
                    $v['username'],//下单人
                    $v['phone'],
                    $v['buy_phone'],
                    $v['price_name'],
                    $v['name'],
                    "\t" . $v['id_num'],
                    ($v['id_num'] and $age && $age < 99) ? $age : '',
                    $v['sex'] == 1 ? '男' : '女',
                    $v['shop_name'],
                    $v['meeting_place'],
                    $v['message'] ?: '',
                );
            }

            $out->out($file_name, $head, $info, $row1);
            //附表
//            $data = $this->_getPlayExcerciseCodeTable()->getEventPrice(['play_excercise_code.eid' => $id, 'is_other' => 1]);
//            $attach_title = ['', '', '', '附表', '', '', ''];
//            $attach_head = ['序号', '收费项目', '下单人', '数量', '单价', '联系电话', '备注'];
//
//            $attach_info = array();
//            foreach ($data as $v) {
//                $attach_info[] = array(
//                    $v['id'],
//                    $v['price_name'],
//                    $v['username'],
//                    1,//类别
//                    $v['price'],
//                    $v['phone'],
//                    $v['mark'],//下单人
//                );
//            }
//
//            $out->outdouble($file_name, $head, $info, $row1, $row2, $attach_title, $attach_head, $attach_info);
            exit;
        }


        return array(
            'data' => $data,
            'excerciseData' => array(iterator_to_array($excerciseData)),  //处理变更成了对象的bug
            'pageData' => $paginator->getHtml(),
        );

    }

    //活动详情
    public function detailsAction()
    {

    }

    public function savepersonAction()
    {
        $uid = (int)$this->getPost('uid');
        $order_sn = (int)$this->getPost('order_sn');
        $name = $this->getPost('name');
        $id_num = $this->getPost('id_num');

        if (empty($id_num) || (strlen($id_num) != 15 && strlen($id_num) != 18)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '身份证格式错误'));
        }
        $playUser = $this->_getPlayUserTable();
        $userData = $playUser->get("uid={$uid}");
        if (empty($userData)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '用户不存在'));
        }

        $checkId = $this->checkCardId($id_num);

        if ($checkId['errNum'] != 0) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '身份证号码不合法'));
        }

        $birth = date('Ymd', strtotime($checkId['retData']['birthday']));

        if ($checkId['retData']['sex'] == 'M') {
            $sex = 1;
        } else {
            $sex = 2;
        }

        $userAssociates = $this->_getPlayUserAssociatesTable();

        $uacit = $userAssociates->get(['id_num' => $id_num]);

        if (!$uacit) {
            $status = $userAssociates->insert(array('uid' => $uid, 'name' => $name, 'sex' => $sex, 'birth' => $birth, 'id_num' => $id_num));
            if ($status) {
                $id = $userAssociates->getlastInsertValue();
            }
        } else {
            $id = $uacit->associates_id;
        }

        if ($id) {
            $order = $this->_getPlayOrderInfoTable()->get(['order_sn' => $order_sn]);

            $oi['order_sn'] = $order_sn;
            $oi['coupon_id'] = $order->coupon_id;
            $oi['name'] = $name;
            $oi['sex'] = $sex;
            $oi['birth'] = $birth;
            $oi['id_num'] = $id_num;
            $oi['insure_status'] = 1;
            $order_insure = $this->_getPlayOrderInsureTable();
            $status = $order_insure->insert(array('associates_id' => $id, 'order_sn' => $order_sn, 'name' => $name, 'sex' => $sex, 'birth' => $birth, 'id_num' => $id_num));
            if ($status) {
                $last_id = $order_insure->getlastInsertValue();
                return $this->jsonResponsePage(array('status' => 1, 'message' => $last_id));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '添加失败'));
            }
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '添加失败'));
        }
    }

    /**
     * 快速修改字段值
     * @param array $_data 列表数组
     * @return mixed
     */
    public function quickmodifyvalAction()
    {
        $sKey = trim($this->getPost('key')); //查询条件，对应数据库字段
        $sVal = trim($this->getPost('val')); //关键字
        $nID = trim($this->getPost('id')); //数据ID
        $type = trim($this->getPost('type', 0)); //类型 1为新增出行人

        if (!is_numeric($nID) || !$sKey || !$sVal) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '参数错误'));
        }

        if ($sKey == 'id_num') {
            if (empty($sVal) || (strlen($sVal) != 15 && strlen($sVal) != 18)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => "身份证格式错误"));
            }
        }

        $map[$sKey] = $sVal;

        $res = $this->_getPlayOrderInsureTable()->update($map, array('insure_id' => $nID));

        if ($res != false) {
            return $this->jsonResponsePage(array('status' => 1, 'data' => $sVal, 'message' => '操作成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => "操作失败"));
        }
    }


    public function checkCardId($code)
    {

        return Idverification::isCard($code);
        $header = 'apikey: 1c6b3fbd2dfc45aeb5c0b80c4cf4c7f0';

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $header,
                'timeout' => 60
            )
        ));
        $data = file_get_contents("http://apis.baidu.com/apistore/idservice/id?id=" . $code, false, $context);
        if (!$data) {
            return false;
        } else {
            $data = json_decode($data, true);
            return $data;
        }
    }


    //批量投保
    public function policyAction()
    {
        $start_time = $this->getQuery('start');// 保险开始时间
        $end_time = $this->getQuery('end');//保险结束时间
        $eid = (int)$this->getQuery('id', 0);

        if ($eid == '') {
            return $this->_Goto("请选择要操作的场次");
        }

        //查询该场次下所有订单号
        $order_data = $this->_getPlayOrderInfoTable()->fetchAll(array('coupon_id' => $eid));
        $order_sn = '';
        foreach ($order_data as $v) {
            $order_sn .= $v['order_sn'] . ',';
        }
        $order_sn = rtrim($order_sn, ',');

        $baoyou = new InsuranceController();
        $res = $baoyou->toubaoAction($order_sn);
        return $res;
    }

    //活动订单列表
    public function orderlistAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageNum = (int)$this->getQuery("pageNum", 10);
        $start = ($page - 1) * $pageNum;

        $order = "play_order_info.order_sn DESC";
        $where = "play_order_info.order_status=1 AND play_order_info.order_type = 3";

        $bid              = (int)$this->getQuery("bid", 0);//活动id
        $coupon_name      = trim($this->getQuery("coupon_name"), ''); //活动名称
        $coupon_id        = (int)$this->getQuery("coupon_id", 0);//场次id
        $order_sn         = $this->getQuery('order_sn', '');
        $activity_type    = (int)$this->getQuery('activity_type', 0);
        $username         = trim($this->getQuery('username'));
        $phone            = trim($this->getQuery('phone'));
        $buy_start        = trim($this->getQuery('buy_start'));
        $buy_end          = trim($this->getQuery('buy_end'));
        $order_status     = (int)$this->getQuery('order_status', 0);
        $out              = (int)$this->getQuery('out', 0);                 // 是否导出
        $event_start_time = trim($this->getQuery('event_start_time', 0));   // 是否导出
        $event_end_time   = trim($this->getQuery('event_end_time', 0));     // 是否导出

        $city = $this->chooseCity();

        if ($bid) {
            $where = $where . " AND play_order_info.bid = {$bid}";
        }

        if ($coupon_name) {
            $where = $where . " AND play_order_info.coupon_name like '%{$coupon_name}%'";
        }

        if ($buy_start) {
            $buy_start = strtotime($buy_start);
            $where = $where . " AND play_order_info.dateline >= {$buy_start}";
        }

        if ($buy_end) {
            $buy_end = strtotime($buy_end) + 3600 * 24;
            $where = $where . " AND play_order_info.dateline <= {$buy_end}";
        }

        if ($city) {
            $where = $where . " AND play_excercise_event.city = '{$city}'";
        }

        if ($username) {
            $where = $where . " AND play_order_info.username like '%{$username}%'";
        }

        if ($phone) {
            $where = $where . " AND play_order_info.phone ='{$phone}'";
        }

        if ($coupon_id) {
            $where = $where . " AND play_order_info.coupon_id ='{$coupon_id}'";
        }

        if ($order_sn) {
            $order_sn = (int)preg_replace('|[a-zA-Z/]+|', '', $order_sn);
            $where = $where . " AND play_order_info.order_sn = " . $order_sn;
        }

        //活动类型
        if ($activity_type) {
            $activity_type = $activity_type - 1;
            $where = $where . " AND play_excercise_event.customize = {$activity_type}";
        }

        // 活动开始时间下限
        if ($event_start_time) {
            $event_start_time = strtotime($event_start_time);
            $where = $where . " AND play_excercise_event.start_time >= {$event_start_time}";
        }

        // 活动开始时间上限
        if ($event_end_time) {
            $event_end_time = strtotime($event_end_time) + 86399;
            $where = $where . " AND play_excercise_event.start_time <= {$event_end_time}";
        }

        if ($order_status) {
            if ($order_status == 1) {
                $where .= " and play_order_info.pay_status<2";
            } else if ($order_status == 2) {
                $where .= " and ((play_order_info.buy_number > play_order_info.back_number + play_order_info.backing_number) AND play_excercise_event.start_time > " . time() . ")";
            } else if ($order_status == 3) {
                $where .= " and  play_order_info.use_number > 0";
            } else if ($order_status == 4) {
                $where .= " and (play_order_info.buy_number = play_order_info.back_number + play_order_info.backing_number)";
            } else if ($order_status == 5) {
                $where .= " and play_order_info.pay_status = 3";
                //$where .= " and (play_order_info.buy_number > play_order_info.back_number + play_order_info.backing_number)";//有退款
            } else {
                return $this->_Goto('非法操作');
            }
        }

        $sql = "SELECT
	play_order_info.order_sn,
	play_order_info.dateline,
    play_order_info.account_money,
    play_order_info.trade_no,
    play_order_info.pay_status,
    play_order_info.account_type,
    play_order_info.voucher,
    play_order_info.real_pay,
	play_order_info.bid,
	play_order_info.coupon_name,
    play_order_info.username,
    play_order_info.user_id,
    play_excercise_event.city AS order_city,
    play_order_info.account,
    play_order_info.phone,
    play_order_info.bid,
    play_order_info.buy_number,
    play_order_info.back_number,
    play_order_info.use_number,
    play_order_info.backing_number,
    play_order_info.coupon_id,
	play_excercise_event.shop_id,
    play_excercise_event.shop_name,
    play_excercise_event.start_time,
    play_excercise_event.end_time,
    play_excercise_event.customize,
    play_excercise_event.join_number,
    play_excercise_event.least_number,
     play_excercise_event.open_time,
      play_excercise_event.over_time,
    play_order_insure.product_code
FROM
	play_order_info
LEFT JOIN play_excercise_event ON  play_excercise_event.id = play_order_info.coupon_id
LEFT JOIN play_order_insure ON play_order_insure.order_sn = play_order_info.order_sn
WHERE $where GROUP BY play_order_info.order_sn ORDER BY $order";


        $adapter = $this->_getAdapter();

        if ($out) {
            $city = CityCache::getCities();
            $outData = $this->query($sql);
            $out = new OutPut();
            $file_name = date('Y-m-d H:i:s', time()) . '_活动订单列表.csv';
            $head = array(
                '交易号',
                '交易时间',
                '交易渠道',
                '城市',
                '活动ID',
                '场次ID',
                '商品订单号',
                '活动名称',
                '套系名称',
                '购买数量',
                '购买金额',
                '代金券金额',
                '已使用金额',
                '等待退款金额',
                '已退款金额',
                '对方账户',
                '用户名',
                '手机号',
                '用户ID',
                '场次开始时间',
                '场次结束时间',
            );
            $info = array();

            $tradeWay = array(
                'weixin' => '微信',
                'union' => '银联',
                'alipay' => '支付宝',
                'account' => '用户账户',
                'new_jsapi' => '新微信网页',
            );

            foreach ($outData as $v) {

                $people = $adapter->query("SELECT
	SUM(if(play_excercise_code.accept_status = 3, play_excercise_code.back_money, 0)) AS back_money,
	SUM(play_excercise_code.back_money) AS all_back_money,
	SUM(if(play_excercise_code.status = 1, play_excercise_code.price, 0)) AS use_price,
	count(if(play_excercise_price.is_other=0 AND play_excercise_code.status <2 ,true,null)) AS traveller_num
FROM
	play_excercise_code
LEFT  JOIN  play_excercise_price ON play_excercise_price.id=play_excercise_code.pid
WHERE 	play_excercise_code.order_sn = ?", array($v['order_sn']))->current();


                //购买的收费项
                $price = $adapter->query("SELECT
	play_excercise_price.price_name
FROM
	play_excercise_code
LEFT  JOIN  play_excercise_price ON play_excercise_price.id=play_excercise_code.pid
WHERE 	play_excercise_code.order_sn = ?
GROUP BY play_excercise_code.pid
", array($v['order_sn']));
                $pName = array();
                foreach ($price as $p) {
                    $pName[] = $p->price_name;
                }


                $info[] = array(
                    "\t" . $v['trade_no'],
                    date('Y-m-d H:i:s', $v['dateline']),
                    $tradeWay[$v['account_type']],
                    $city[$v['order_city']],
                    $v['bid'],
                    $v['coupon_id'],
                    'WFT' . (int)$v['order_sn'],
                    $v['coupon_name'],
                    implode(';', $pName),
                    $v['buy_number'],
                    bcadd($v['real_pay'], $v['account_money'], 2),
                    $v['voucher'],
                    $people->use_price,
                    bcsub($people->all_back_money, $people->back_money, 2),
                    $people->back_money,
                    $v['account'],
                    $v['username'],
                    $v['phone'],
                    $v['user_id'],
                    date('Y-m-d H:i:s', $v['start_time']),
                    date('Y-m-d H:i:s', $v['end_time']),
                );
            }
            $out->out($file_name, $head, $info);
            exit;
        }

        //创建分页

        $sql_list = $sql . " LIMIT
{$start}, {$pageNum}";

        $queryData = $this->query($sql_list);

        $data = array();
        $placeCache = new PlaceCache();
        $baoyou = new Baoyou();
        foreach ($queryData as $query) {

            $people = $adapter->query("SELECT
	count(*) AS total,
	count(if(play_excercise_price.is_other=0,true,null)) AS traveller_num,
    count(if(play_excercise_price.is_other=1,true,null)) AS other_num,
    count(if(play_excercise_code.use_free_coupon>0,true,null)) AS free_num,
    SUM(if(play_excercise_code.status=1 AND play_excercise_code.back_money = 0 ,play_excercise_code.price,null)) AS can_use_back_money,
    play_excercise_price.person
FROM
	play_excercise_code
LEFT  JOIN  play_excercise_price ON play_excercise_price.id=play_excercise_code.pid
WHERE 	play_excercise_code.order_sn = ? limit 1", array($query['order_sn']))->current();

            $data[] = array(
                'order_sn' => $query['order_sn'],
                'dateline' => $query['dateline'],
                'account_money' => $query['account_money'],
                'real_pay' => $query['real_pay'],
                'bid' => $query['bid'],
                'coupon_id' => $query['coupon_id'],
                'coupon_name' => $query['coupon_name'],
                'pay_status' => $query['pay_status'],
                'username' => $query['username'],
                'user_id' => $query['user_id'],
                'phone' => $query['phone'],
                //'shop_id' => $query['shop_id'],
                'shop_name' => $query['shop_name'],
                'customize' => $query['customize'],
                'join_number' => $query['join_number'],
                'use_number' => $query['use_number'],
                'back_number' => $query['back_number'],
                'backing_number' => $query['backing_number'],
                'least_number' => $query['least_number'],
                'traveller_num' => $people->traveller_num,  //购买份数
                'person' => $people->person, //每份对应的人数
                'start_time' => $query['start_time'],
                'buy_number' => $query['buy_number'],
                'other_num' => $people->other_num,
                'free_num'  => $people->free_num,
                'circle' => $placeCache->getPlaceCircle($query['shop_id']),
                'days' => $baoyou->getProductInfo($query['product_code'])['DayRange'],
                'can_use_back_money' => $people['can_use_back_money']
            );
        }

        $count_sql = "SELECT
	count(*) as count_num
FROM
	play_order_info
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id
WHERE
	 $where";
        $count = $adapter->query($count_sql, array())->current()->count_num;

        //创建分页

        $url = '/wftadlogin/excercise/orderlist';
        $paginator = new Paginator($page, $count, $pageNum, $url);


        //统计统计
        $sql_count_no_pay = "SELECT
	count(DISTINCT play_order_info.order_sn) as count_num
FROM
	play_order_info
LEFT JOIN play_excercise_event ON  play_excercise_event.id = play_order_info.coupon_id
LEFT JOIN play_order_insure ON play_order_insure.order_sn = play_order_info.order_sn
WHERE
$where AND play_order_info.pay_status< 2";

        $noPayCountData = $this->query($sql_count_no_pay)->current();
        $noPayNum = $noPayCountData['count_num'];

        $back_money_sql = "SELECT
	 SUM(play_excercise_code.back_money) as back_money
FROM
	play_excercise_code
LEFT JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id
WHERE
$where AND (play_excercise_code.status = 2 OR play_excercise_code.status = 3)";

        $backMoneyData = $this->query($back_money_sql)->current();
        $outSum = $backMoneyData['back_money'];

        $sql_count = "SELECT
	count(*) as count_num,
	SUM(real_pay) AS real_pay_money,
	SUM(account_money) AS balance_money
FROM
(SELECT play_order_info.order_sn, play_order_info.real_pay, play_order_info.account_money FROM
	play_order_info
LEFT JOIN play_excercise_code ON play_excercise_code.order_sn = play_order_info.order_sn
LEFT JOIN play_excercise_event ON play_excercise_event.id = play_order_info.coupon_id
WHERE
$where GROUP BY play_order_info.order_sn) AS list_order";

        $countData = $this->query($sql_count)->current();


        return new ViewModel([
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            'order_sum' => $count,
            'pay_sum' => $count - $noPayNum,
            'income' => bcadd($countData['real_pay_money'], $countData['balance_money'], 2),
            'outSum' => $outSum ?: 0,
        ]);
    }

    //验证码验证 5个请求源
    public function validateAction()
    {
        $order_sn = $this->getQuery('order_sn');
        $code = (int)$this->getQuery('code') == '' ? (int)$this->getPost('code') : (int)$this->getQuery('code');
        $type = (int)$this->getQuery("type");

        if (!$order_sn) {
            if (!$code) {
                return $this->_Goto('缺少参数');
            }
        }

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }


        $use = new UseExcerciseCode();


        if ($type == 1) {
            $orderData = $this->_getPlayOrderInfoTable()->get(array("order_sn" => $order_sn));

            if (!$orderData) {
                return $this->_Goto("订单不存在");
            }
            if ($orderData->pay_status < 2) {
                return $this->_Goto("该订单未付款");
            }
            if ($orderData->pay_status == 3 || $orderData->pay_status == 4) {
                return $this->_Goto('该订单退款中或已退款');
            }
            if ($orderData->pay_status == 5) {
                return $this->_Goto('该订单已使用');
            }


            if ($code) { //单个code
                $orderData = $this->_getPlayExcerciseCodeTable()->get(array("code" => $code));

                if (!$orderData) {
                    return $this->_Goto('验证码不存在');
                }

                if ($orderData->status == 1) {
                    return $this->_Goto("验证码已验证");
                }

                if ($orderData->status == 2 || $orderData->status == 3) {
                    return $this->_Goto("验证失败，验证码已退费或已使用");
                }

                $res = $use->UseCode($code);
                return $this->_Goto($res['message']);
            } else { //单个order
                $res = $use->UseOrder($order_sn);
                return $this->_Goto($res['message']);
            }
        } else {

            //批量验证
            if ($code) {
                $order_sns = rtrim($code, ',');
            } else {
                $order_sns = rtrim($order_sn, ',');
            }
            if (!$order_sns) {
                return $this->_Goto("没有选中订单");
            }
            if ($code) {
                $sql = "select code from  play_excercise_code WHERE status = 0 AND id in ($order_sns)";
                $res = $this->query($sql);
                foreach ($res as $v) {
                    $data = $use->UseCode($v['code']);
                    if ($data['status'] == 0) {
                        return $this->_Goto($data['message']);
                    }
                }
                return $this->_Goto("已使用");
            } else {
                $res = $use->UseOrder($order_sn);
                return $this->_Goto($res['message']);
            }
        }
    }


    //退费
    public function backAction()
    {
        $order_sn = trim($this->getQuery('order_sn'));
        $cid = (int)$this->getQuery('cid');
        $type = (int)$this->getQuery("type");

        $db = $this->_getAdapter();
        if (!$order_sn) {
            if (!$cid) {
                return $this->_Goto('缺少参数');
            }
        }

        if (!in_array($type, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        $time = time();

        if ($type == 1) {
            $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

            if (!$orderData) {
                return $this->_Goto("订单数据不存在");
            }

            if ($orderData->pay_status < 2) {
                return $this->_Goto("该订单未付款");
            }

            //单个验证码退费
            if ($cid) {
                $codeData = $this->_getPlayExcerciseCodeTable()->get(array('id' => $cid));

                if ($codeData->status == 2 || $codeData->status == 3) {
                    return $this->_Goto('该验证码已退费');
                }

                $back = new OrderExcerciseBack();

                $res = $back->backIng($order_sn, $codeData->code, 2);

                //删除某一个出行人
                $this->_getAdapter()->query("delete from   play_order_insure where order_sn=? order BY insure_status ASC  limit {$codeData->person}", array($order_sn));


                if ($res['status'] == 1) {
                    return $this->_Goto('退费成功');
                } else {
                    return $this->_Goto('退费失败:' . $res['message']);
                }

            } else {//整个订单退费

//                if ($orderData->pay_status == 3 || $orderData->pay_status == 4) {
//                    return $this->_Goto('该订单退款中或已退款');
//                }

                $back = new OrderExcerciseBack();
                $codes = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order_sn, 'status' => 0));

                foreach ($codes as $v) {
                    $res = $back->backIng($order_sn, $v->code, 2);
                }

                if ($res['status'] == 1) {
                    return $this->_Goto('退费成功');
                } else {
                    return $this->_Goto('退费失败');
                }


            }
        }

        //批量退费
        if ($cid) {
            $order_sns = rtrim($cid, ',');
        } else {
            $order_sns = rtrim($order_sn, ',');
        }


        if (!$order_sns) {
            return $this->_Goto("没有选中订单");
        }

        if ($cid) {
            $back = new OrderExcerciseBack();
            $code_data = $this->_getPlayExcerciseCodeTable()->fetchAll(array('status in (1,4)', "id in ($order_sns)"));

            foreach ($code_data as $v) {
                $res = $back->backIng($order_sn, $v->code, 2);
            }
            if ($res['status'] == 1) {
                return $this->_Goto('批量退费成功');
            } else {
                return $this->_Goto('批量退费失败');
            }

        } else {

            $back = new OrderExcerciseBack();
            $order_data = $db->query("select * from  play_order_info  where order_sn in ($order_sns)", array());

            $msg = '';
            foreach ($order_data as $order) {


                if ($order->pay_status == 3 || $order->pay_status == 4) {
                    $msg .= "{$order->order_sn}该订单退款中或已退款";
                }

                $codes = $this->_getPlayExcerciseCodeTable()->fetchAll(array('order_sn' => $order->order_sn, 'status' => 0));
                foreach ($codes as $v) {
                    $res = $back->backIng($order->order_sn, $v->code, 2);
                }

            }

            return $this->_Goto('退费成功' . $msg);

        }
    }

    public function orderinfoAction()
    {
        $order_sn = (int)$this->getQuery('order_sn');
        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderData) {
            return $this->_Goto('该订单不存在');
        }

        $service_member = new Member();
        $data_member    = $service_member->getMemberData($orderData['user_id']);

        $sql = "select ee.no,ee.insurance_id,eb.name,eb.id,eb.teacher_type,eb.start_age,eb.end_age,eb.meeting,eb.phone,ee.shop_name,ee.welfare,ee.full_price,ee.less_price,ee.start_time,ee.end_time,ee.join_number,ee.join_number,ee.id as eid from play_excercise_base as eb left join play_excercise_event as ee on ee.bid = eb.id where ee.id = {$orderData->coupon_id}";

        $info = $this->query($sql);

        $excerciseData = array();

        foreach ($info as $k => $v) {
            $excerciseData[$k] = $v;
        }

        $excercisePrice = $this->query("select ep.id, ep.is_other, ep.price_name, ep.price, ep.free_coupon_need_count, ec.back_money, ec.use_free_coupon, ec.status from play_excercise_code as ec left join play_excercise_price as ep on ep.id = ec.pid where ec.order_sn = {$order_sn} order by ep.is_other asc, ec.pid asc");

        $backAll   = 0;
        $backOther = 0;
        $data_pay_price_count = 0;
        $data_price           = array();
        $data_free_item       = array(
            'free_money'  => 0.00,
            'free_coupon' => 0,
        );

        foreach ($excercisePrice as $k => $v) {
            if (in_array($v['status'], array(2, 3))) {
                if ($v['use_free_coupon'] == 0) {
                    $backAll += $v['back_money'];
                } else if ($v['use_free_coupon'] > 0) {
                    $backOther += (int)$v['use_free_coupon'];
                }
            }

            if ($v['use_free_coupon'] > 0) {
                $data_free_item['free_money'] = $data_free_item['free_money'] + $v['price'];
                $data_free_item['free_coupon']= $data_free_item['free_coupon']+ $v['use_free_coupon'];
            }

            if ($data_price[$v['id']]) {
                $data_price[$v['id']]['buy_num']     = (int)$data_price[$v['id']]['buy_num'] + 1;
                $data_price[$v['id']]['price_count'] = $data_price[$v['id']]['price_count'] + $v['price'];
            } else {
                $data_price[$v['id']] = $v;
                $data_price[$v['id']]['buy_num']     = 1;
                $data_price[$v['id']]['price_count'] = $v['price'];
            }

            $data_pay_price_count = $data_pay_price_count + $v['price'];
        }

        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        foreach ($baoyoulist as $bl) {
            if ($bl['RateCode'] == $excerciseData[0]['insurance_id']) {
                $ratecode = $bl['PlanName'];
                $rateday = $bl['DayRange'] . '天';
            }
        }

        $change_back = false;
        if (in_array($_COOKIE['id'], array(2797, 1317))) {
            $change_back = true;
        }


        //驳回退款 + 操作记录
        $sql_back = "SELECT
	play_order_action.*,
	play_excercise_code.`status` AS code_status,
	play_excercise_code.`accept_status`
FROM
	play_order_action
LEFT JOIN play_excercise_code ON play_excercise_code.id = play_order_action.code_id
WHERE
	play_order_action.order_id = {$order_sn}";

        $actionData = array();
        $map = array('right' => array(), 'middle' => array(), 'left' => array());
        $resBack = $this->_getAdapter()->query($sql_back, array());

        foreach (array_reverse($resBack->toArray()) AS $v) {
            $flag = 0;
            if ($v['play_status'] == 3 && $v['code_status'] == 3 && $v['accept_status'] == 0) {
                if (!in_array($v['code_id'], $map['right'])) {
                    array_push($map['right'], $v['code_id']);
                    $flag = 1;
                }
            }

            if ($v['play_status'] == 14 && $v['code_status'] == 1 && $v['accept_status'] == 1) {
                if (!in_array($v['code_id'], $map['middle'])) {
                    array_push($map['middle'], $v['code_id']);
                    $flag = 2;
                }
            }

            if ($v['play_status'] == 7 && $v['accept_status'] == 2) {
                if (!in_array($v['code_id'], $map['left'])) {
                    array_push($map['left'], $v['code_id']);
                    $flag = 3;
                }
            }
            $actionData[] = array(
                'dateline' => $v['dateline'],
                'action_note' => $v['action_note'],
                'play_status' => $v['play_status'],
                'action_user_name' => $v['action_user_name'],
                'code_id' => $v['code_id'],
                'order_id' => $v['order_id'],
                'back_flag' => $flag,
            );
        }

        return array(
            'data_member'          => $data_member,
            'orderData'            => $orderData,
            'actionData'           => array_reverse($actionData),
            'excerciseData'        => $excerciseData,
            'data_price'           => $data_price,
            'data_pay_price_count' => $data_pay_price_count,
            'data_free_item'       => $data_free_item,
            'backAll'              => $backAll,
            'backOther'            => $backOther,
            'ratecode'       => $ratecode,
            'rateday' => $rateday,
            'change_back' => $change_back,
        );
    }


    public function codeinfoAction()
    {
        $order_sn = (int)$this->getQuery('order_sn');
        $codedata = $this->_getPlayExcerciseCodeTable()->getCodeList(array("play_excercise_code.order_sn" => $order_sn));

        $extraData = array();
        $data = array();
        foreach ($codedata as $k => $v) {
            if ($v['is_other'] == 0) {
                array_push($data, $v);
            } else {
                array_push($extraData, $v);
            }
        }

        return array(
            'codeData' => $data,
            'extraData' => $extraData,
        );
    }

    //新增订单出行人信息
    public function policyinfoAction()
    {
        $order_sn = (int)$this->getQuery('order_sn', 0);
        $order_insure_data = $this->_getPlayOrderInsureTable()->getPersonList('', '', "play_order_info.order_sn={$order_sn}");

        $order_data = $this->_getPlayOrderInfoTable()->get(['order_sn' => $order_sn]);

        return array(
            'data' => $order_insure_data,
            'order_data' => $order_data
        );

    }

    //删除出行人
    public function deltravellerAction()
    {
        $id = rtrim($this->getQuery('id', '@'));
        $ids = explode('@', $id);

        $ids = array_filter($ids);

        $type = (int)$this->getQuery('type', 0);
        $result = $this->dodel($ids);
        if ($result) {
            $url = $_COOKIE['referer_url'] ? $_COOKIE['referer_url'] : $_SERVER["HTTP_REFERER"];
            setcookie('referer_url', '', time() - 3600);
            return $this->_Goto('执行完毕', $url);
        }
        exit;
    }

    public function dodel($ids)
    {
        $data = $this->_getPlayOrderInsureTable()->get(array('insure_id' => $ids[0]));
        if (!$data) {
            $show = '该出行人不存在';
        } else {
            $res = $this->_getPlayOrderInsureTable()->update(array('name' => '', 'id_num' => ''), array('insure_id' => $ids[0]));
            if ($res) {
                $show = '操作成功';
                return $this->_Goto($show);
            } else {
                $show = '操作失败';
                return $this->_Goto($show);
            }
        }

        unset($ids[0]);
        if (count($ids)) {
            echo $show;
            $redirect = '/wftadlogin/excercise/deltraveller?&id=' . implode('@', $ids);
            echo "<script>setTimeout(function(){window.location.href='" . $redirect . "'}, 1000);</script>";
            return false;
        } else {
            return true;
        }

    }

    public function getEventAction()
    {
        $k = $this->getQuery('k');

        if ($k) {
            $where = array(
                'play_excercise_base.city = ?' => $_COOKIE['city'],
                ' name like ? or play_excercise_event.no = ?  or play_excercise_event.id = ? ' => array('%' . $k . '%', $k, $k),
            );
            if ($this->getAdminCity() == 1) {
                unset($where['']);
            }
            $data = $this->_getPlayExcerciseEventTable()->getEventList(0, 10, ['*'], $where, array());
            $res = array();
            if (count($data)) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val['id'],
                        'name' => $val['name'] . ' 第' . $val['no'] . '期【ID:' . $val['id'] . '】(' . date('Y-m-d', $val['start_time']) . '-' . date('Y-m-d', $val['end_time']) . ')',
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 1, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    //更新最低价
    public function updatelow_money($bid)
    {
        $db = $this->_getAdapter();
        $db->query("
        UPDATE play_excercise_base
SET low_price = (
	SELECT
		MIN(price)
	FROM
		play_excercise_price
	WHERE
		bid = play_excercise_base.id
	AND play_excercise_price.is_close = 0
	AND play_excercise_price.is_other = 0
)
WHERE  play_excercise_base.id=?
", array($bid));
    }

    private function checkOrderExist ($pid) {
        $pdo = $this->_getAdapter();

        $sql = " SELECT * FROM play_excercise_code LEFT JOIN play_order_info ON play_order_info.order_sn = play_excercise_code.order_sn WHERE play_excercise_code.pid = ? AND play_order_info.order_status = 1";
        $data_return_exist = $pdo->query($sql, array($pid))->current();

        return $data_return_exist ? false : true;
    }
}
