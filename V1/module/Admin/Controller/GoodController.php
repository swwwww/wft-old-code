<?php

namespace Admin\Controller;

use Deyi\Baoyou\Baoyou;
use Deyi\Contract\Contract;
use Deyi\Coupon\Good;
use Deyi\GetCacheData\GoodCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\Validation;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;
use Deyi\GetCacheData\PlaceCache;

class GoodController extends BasisController
{
    use JsonResponse;

    public function newAction()
    {

        $gid = (int)$this->getQuery('gid');
        $type = $this->getQuery('type');
        $page = (int)$this->getQuery('page');
        $page_num = (int)$this->getQuery('page_num');

        if (!$type) {
            $type = 'basis';
        }

        $goodData = null;

        if ($gid) {
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
        }

        //标签
        $config_special_labels = $config_special_labels = $this->_getPlayTagsTable()->fetchAll(array(
            'tag_city' => $this->getCity(),
            'type' => 1
        ), array('sort' => 'desc'))->toArray();

        // 获取短信模板类型
        $config_message_type = array(
            array(
                'id' => 1,
                'message_type_name' => '通用类型',
            ),
            array(
                'id' => 2,
                'message_type_name' => '酒店类商品',
            ),
            array(
                'id' => 3,
                'message_type_name' => '预约使用类商品',
            ),
            array(
                'id' => 4,
                'message_type_name' => '智游宝',
            ),
        );

        $config_payment_type = array(
            array(
                'id' => 0,
                'payment_type_name' => '全部',
            ),
            array(
                'id' => 1,
                'payment_type_name' => '不支持余额支付',
            ),
            array(
                'id' => 2,
                'payment_type_name' => '仅支持余额支付',
            ),
        );

        if ($type === 'together') {//非合作商品
            $city = $_COOKIE['city'];

            //标签
            $tags = $this->_getPlayTagsTable()->fetchAll(array('tag_city' => $city));

            //分类
            $link_label = array();
            $labels = $this->_getPlaylabelTable()->fetchAll(array(
                'city' => $city,
                'label_type in (2, 3)',
                'status > 0'
            ));

            //商家 默认为玩翻天 organizer_id
            $vm = new ViewModel(
                array(
                    'goodData' => $goodData,
                    'tags' => $tags,
                    'link_label' => $link_label,
                    'labels' => $labels,
                    'config_message_type' => $config_message_type,
                    'config_payment_type' => $config_payment_type,
                    'config_special_labels' => $config_special_labels,
                    'special_labels_str'=>implode(',',json_decode($goodData->special_labels,true))

                )
            );

            $vm->setTemplate('admin/good/good-together.phtml');

            return $vm;
        }

        if ($type === 'basis') { //基本信息

            $phone = null;
            if ($goodData && $goodData->phone) {
                $phone = $goodData->phone;
            }

            //标签
            $link_tag = array();
            if ($gid) {
                $city = $goodData->city;
                $link_tag = $this->_getPlayTagsLinkTable()->fetchLimit(0, 100, array('tag_id'),
                    array('link_id' => $gid, 'tag_type' => 1))->toArray();
            } else {
                $city = $_COOKIE['city'];
            }
            $tags = $this->_getPlayTagsTable()->fetchAll(array('tag_city' => $city));

            //分类
            $link_label = array();
            if ($gid) {
                $city = $goodData->city;
                $link_label = $this->_getPlaylabelLinkerTable()->fetchLimit(0, 100, array('label_id' => 'lid'),
                    array('object_id' => $gid, 'link_type' => 2))->toArray();
            } else {
                $city = $_COOKIE['city'];
            }
            $labels = $this->_getPlaylabelTable()->fetchAll(array(
                'city' => $city,
                'label_type in (2, 3)',
                'status > 0'
            ));


            $vm = new ViewModel(
                array(
                    'goodData' => $goodData,
                    'phone' => $phone,
                    'link_tag' => $link_tag,
                    'tags' => $tags,
                    'link_label' => $link_label,
                    'labels' => $labels,
                    'config_message_type' => $config_message_type,
                    'config_payment_type' => $config_payment_type,
                    'config_special_labels' => $config_special_labels,
                    'special_labels_str'=>implode(',',json_decode($goodData->special_labels,true))
                )
            );

            $vm->setTemplate('admin/good/good-basis.phtml');

            return $vm;
        }

        if (!$gid) {
            return $this->_Goto('非法操作');
        }

        if ($type === 'info') { //商品描述
            $vm = new ViewModel(
                array(
                    'goodData' => $goodData,
                )
            );
            $vm->setTemplate('/admin/good/good-info.phtml');

            return $vm;
        }

        if ($type === 'price') { //商品套系

            //合同限制套系
            $contractLinkData = $this->_getPlayContractLinkPriceTable()->fetchAll(array(
                'good_id' => $gid,
                'status >= ?' => 1
            ));

            //价格系列
            $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $gid), ['id' => 'desc']);

            //套系
            //$goodInfoData = $this->_getPlayGameInfoTable()->getUsedOrganizer(0, 1000, array(), array('play_game_info.gid' => $gid, 'play_game_info.status > 0'));
            $good_info_sql = " SELECT
play_game_info.*,
play_code_used.organizer_name
From play_game_info
LEFT JOIN play_code_used ON (play_code_used.good_info_id = play_game_info.id AND play_game_info.status > 0)
WHERE play_game_info.gid = {$gid} "; //AND play_game_info.status > 0

            $goodInfoData = $this->query($good_info_sql);


            //商家 与 分店
            $marketData = $this->_getPlayOrganizerTable()->get(array('id' => $goodData->organizer_id));
            $branchData = $this->_getPlayOrganizerTable()->fetchAll(array(
                'branch_id' => $goodData->organizer_id,
                'status > 0'
            ));

            //套系使用商家
            //$infoUsedData = $this->_getPlayCodeUsedTable()->fetchAll(array('good_id' => $gid));

            //获取保险列表
            $baoyou = new Baoyou();
            $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

            $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));

            if ($contractData && !in_array($contractData->contracts_type, array(1, 3))) {
                //return $this->_Goto('该合同类型不存在');
            }

            $tip = '';
            if ($contractData) {
                if ($contractData->contracts_type == 1) {
                    $inventoryData = $this->_getPlayInventoryTable()->fetchAll(array(
                        'good_id' => $gid,
                        'contract_id' => $goodData->contract_id,
                        'inventory_status > ?' => 0
                    ));
                    foreach ($inventoryData as $inventory) {
                        $tip = $tip + $inventory['purchase_number'];
                    }
                }
            }

            if ($tip) {
                $tip = '商品可售数量' . $tip;
            }


            $vm = new ViewModel(
                array(
                    'baoyoulist' => $baoyoulist,
                    'contractLinkData' => $contractLinkData, //合同限制的
                    'goodData' => $goodData, //商品数据
                    'priceData' => $priceData, //价格系列
                    'goodInfoData' => $goodInfoData, //套系
                    'marketData' => $marketData,//商家
                    'branchData' => $branchData, //分店
                    'tip' => $tip,
                    //'infoUsedData' => $infoUsedData, //套系使用商家
                )
            );

            $vm->setTemplate('admin/good/good-price.phtml');

            return $vm;
        }

        if ($type === 'welfare') { //商品福利

            //积分奖励
            //$welfareIntegralData = $this->_getPlayWelfareIntegralTable()->fetchAll(array('object_type' => 2, 'object_id' => $gid));
            $welfareData = array();
            $welShare = $this->_getPlayWelfareIntegralTable()->get(array(
                'object_id' => $gid,
                'object_type' => 2,
                'welfare_type' => 4,
                'status > 0'
            ));
            $welfareData['share'] = $welShare;
            $welPost = $this->_getPlayWelfareIntegralTable()->get(array(
                'object_id' => $gid,
                'object_type' => 2,
                'welfare_type' => 3,
                'status > 0'
            ));
            $welfareData['post'] = $welPost;

            //返利奖励
            $welfareRebateData = $this->_getPlayWelfareRebateTable()->fetchAll(array('gid' => $gid,'from_type'=>1, 'status > 0'));

            //现金券
            $welfareCashData = $this->_getPlayWelfareCashTable()->fetchAll(array('gid' => $gid, 'status > 0'));

            $vm = new ViewModel(
                array(
                    'welfareData' => $welfareData,
                    'welfareRebateData' => $welfareRebateData,
                    'welfareCashData' => $welfareCashData,
                    'goodData' => $goodData,
                )
            );
            $vm->setTemplate('admin/good/good-welfare.phtml');

            return $vm;
        }

        if ($type === 'code') { //商品福利
            if (empty($page)) {
                $page = 1;
            }

            if (empty($page_num)) {
                $page_num = 10;
            }

            $pdo = $this->_getAdapter();
            $data_code_list = $pdo->query(
                " SELECT
	                  play_coupon_code.order_sn,
	                  play_order_info.dateline,
	                  play_coupon_code.use_datetime,
	                  play_user.username,
	                  play_user.phone,
	                  play_game_code.code
                  FROM
	                  play_coupon_code
                  LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn
                  LEFT JOIN play_user ON play_user.uid = play_order_info.user_id
                  LEFT JOIN play_game_info ON play_game_info.id = play_order_info.bid
                  LEFT JOIN play_game_code ON play_coupon_code.id = play_game_code.code_order_id
                  WHERE
	                  play_order_info.coupon_id = ?
                  AND play_order_info.order_status = 1
                  AND (
	                    play_order_info.pay_status = 2
	                    OR play_order_info.pay_status = 5
                  )
                  AND (
	                  play_coupon_code. STATUS = 0
	                  OR play_coupon_code. STATUS = 1
                  )
                  ORDER BY play_order_info.dateline DESC
                  ",
                  array($gid)
            )->toArray();
            $vm = new ViewModel(
                array(
                    'data_code_list' => $data_code_list,
                    'goodData' => $goodData,
                )
            );
            $vm->setTemplate('admin/good/good-orderandcode.phtml');

            return $vm;
        }

        return $this->_Goto('非法操作');
    }

    //保存
    public function saveAction()
    {

        $gid = (int)$this->getPost('gid');
        $type = $this->getQuery('type');

        if ($type === 'basis' || $type === 'together') {//修改 或者 创建非合作商品

            //$goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
            $title = $this->getPost('title');
            $title = htmlspecialchars_decode($title);
            $share_title = $this->getPost('share_title');
            $coupon_vir = $this->getPost('coupon_vir');
            $buy_way = (int)$this->getPost('buy_way');
            $is_hotal = (int)$this->getPost('is_hotal');

            $start_time = strtotime($this->getPost('start_time') . $this->getPost('start_timel')); //上架时间
            $end_time = strtotime($this->getPost('end_time') . $this->getPost('end_timel')); //下架时间

            $phone = $this->getPost('phone'); //电话
            $age_min = $this->getPost('age_min');
            $age_max = $this->getPost('age_max');
            $is_comments_value = $this->getPost('is_comments_value', 0);//备注项是否必填
            $comments_value = $this->getPost('comments_value', '注明您对卖家的留言');
            $is_private_party = $this->getPost('is_private_party', 0);//是否包场
            $need_use_time = $this->getPost('need_use_time', '');//是否选择时间

            $message_type = $this->getPost('message_type', 1);
            $message_custom_content = $this->getPost('message_custom_content', '');
            $special_labels = (array)$this->getPost('special_labels', '');     // 特权标签

            $labels='';
            if (!empty($special_labels)) {
                $labels= json_encode($special_labels, JSON_UNESCAPED_UNICODE);
            }

            $payment_type           = $this->getPost('payment_type', 0);

            if ($need_use_time === '0') {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '是否必选使用日期!'));
            }

            $has_addr = $this->getPost('has_addr', 0);//是否必填收货地址


            $post_area_word = $this->getPost('post_area_word');

            if (!in_array($is_comments_value, array(0, 1)) || !in_array($is_private_party, array(0, 1))) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }

            $validation = new validation();
            if ($validation->strLengthRange($title, 1, 50)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商品名称不正确'));
            }

            if ($end_time < time() || $start_time > $end_time) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '上下架时间不正确'));
            }

            if ($age_max <= $age_min) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '年龄选择不对'));
            }

            $labelIds = $this->params()->fromPost('label'); //分类

            if (!count($labelIds)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '商品必须有一个分类'));
            }

            $data = array(
                'title' => $title,
                'share_title' => $share_title,
                'coupon_vir' => $coupon_vir,
                'is_hotal' => $is_hotal,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'age_min' => $age_min,
                'age_max' => $age_max,
                'phone' => $phone,
                'post_area_word' => $post_area_word,
                'buy_way' => $buy_way,
                'is_comments_value' => $is_comments_value,
                'comments_value' => $comments_value,
                'is_private_party' => $is_private_party,
                'has_addr' => $has_addr,
                'message_type' => $message_type,
                'payment_type' => $payment_type,
                'special_labels'=>$labels,
            );

            if ($need_use_time) {
                $data['need_use_time'] = $need_use_time;
            }

            $city = $this->getPost('city');
            if (!in_array($city, array_flip($this->getAllCities()))) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该城市不存在'));
            }

            if ($message_custom_content) {
                $data['message_custom_content'] = $message_custom_content;
            }

            if ($gid) { //修改

                //判断$limit_low_num 最少购买数量
                $organizerGameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
                if (!$organizerGameData) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '非法'));
                }

                if ($organizerGameData->contract_id) {
                    $sql = "select sum(total_num) as count_num FROM play_contract_link_price WHERE good_id = {$gid} AND status = 1";
                    $count_num = $this->query($sql)->current();
                }

                //非合作 没有虚拟票
                if ($organizerGameData->is_together == 2 && $coupon_vir) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '非合作没有虚拟票'));
                }

                // 同玩 不能有 资格券
                if ($organizerGameData->g_buy == 1) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '同玩的不能有资格券'));
                }

                $status = $this->_getPlayOrganizerGameTable()->update($data, array('id' => $gid));
            }

            if ($type === 'together') {//非合作商品新建
                $data['organizer_id'] = 24;//$this->getPost('organizer_id'); 非合作商家 默认为玩翻天
                $data['city'] = $city;
                $data['dateline'] = time();
                $data['is_together'] = 2;
                $data['status'] = 0;
                $status = $this->_getPlayOrganizerGameTable()->insert($data);
                if ($status) {
                    $gid = $this->_getPlayOrganizerGameTable()->getlastInsertValue();

                    //同时创建默认商品积分
                    $city = $this->getAdminCity();
                    $timer = time();
                    $sql = "INSERT INTO play_welfare_integral (
	id,
	object_id,
	object_type,
	welfare_type,
	`double`,
	limit_num,
	total_num,
	status,
	dateline,
	editor_id,
	editor,
	get_num,
	city
)
VALUES
	(NULL, $gid, 2, 3, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}'),
	(NULL, $gid, 2, 4, 1, 1, 1000, 1, {$timer}, {$_COOKIE['id']}, '{$_COOKIE['user']}', 0, '{$city}')";

                    $this->query($sql);

                }
            }


            /*if ($type === 'basis' && !$gid) {//新建合作商品
                $contract_no = (int)$this->getPost('contract_id');
                $contractData = $this->_getPlayContractsTable()->get(array('id' => $contract_no));
                if (!$contractData) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '该合同部存在'));
                }
                $data['organizer_id'] = $contractData->mid;
                $data['contract_id'] = $contract_no;
                $data['city'] = $city;
                $data['dateline'] = time();
                $data['status'] = 0;
                $status = $this->_getPlayOrganizerGameTable()->insert($data);

                if ($status) {
                    $gid = $this->_getPlayOrganizerGameTable()->getlastInsertValue();

                    $mer = $this->_getPlayContractLinkGoodTable()->get(array('contract_id' => $contract_no, 'good_name' => $title));
                    if ($mer) {
                        $this->_getPlayContractLinkGoodTable()->update(array('good_id' => $gid), array('contract_id' => $contract_no, 'good_name' => $title));
                        $this->_getPlayContractLinkPriceTable()->update(array('good_id' => $gid), array('link_good_id' => $mer->id));
                        $this->_getPlaycodeUsedTable()->update(array('good_id' => $gid), array('link_good_id' => $mer->id));
                    }
                }
            }*/


            //处理 商品的属性和分类
            $tagIds = $this->params()->fromPost('tag'); //属性

            $Good = new Good();

            $Good->doLabel($gid, $labelIds);
            $Good->doWithTag($gid, $tagIds);

            return $this->jsonResponsePage(array('status' => 1, 'message' => '保存成功', 'gid' => $gid));

        }

        // 商品描述、小玩说保存
        if ($type === 'info' && $gid) {

            $validation = new validation();
            $matters = $this->getPost('matters');// 注意事项
            $editor_talk = $this->getPost('editor_talk'); //小玩说
            $information = $this->getPost('editorValue'); //详情
            $cover = $this->getPost('cover');

            if ($validation->strLengthRange($editor_talk, -1, 1000)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '小玩说'));
            }

            $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);
            $cover_status = $cover_class->scaleResizeImage(720, 360);
            if ($cover_status) {
                $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
            }
            $thumbing = $this->getPost('thumb') ? $this->getPost('thumb') : $this->getPost('cover') . '.min.jpg';
            $thumb = $this->getPost('thumb') ? $this->getPost('thumb') : $cover;
            $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
            $surface_status = $surface_class->scaleResizeImage(360, 360);
            if ($surface_status) {
                $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumbing);
            }

            $data = array(
                'information' => $information,
                'matters' => $matters,
                'editor_talk' => $editor_talk,
                'cover' => $cover,
                'thumb' => $thumbing,
            );

            $status = $this->_getPlayOrganizerGameTable()->update($data, array('id' => $gid));

            return $this->jsonResponsePage(array(
                'status' => $status,
                'message' => ($status ? '保存成功' : '失败'),
                'gid' => $gid
            ));

        }

    }


    /**
     * 套系 start
     */

    public function goodAction()
    { //修改套系

        $id = (int)$this->getQuery('id');
        $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $id, 'status = 1'));
        if (!$gameInfo) {
            return $this->_Goto('非法操作');
        }

        $useOrganizerData = $this->_getPlayCodeUsedTable()->get(array(
            'good_id' => $gameInfo->gid,
            'good_info_id' => $id
        ));//使用商家
        $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $gameInfo->gid));//价格系列


        //商家 与 分店
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gameInfo->gid));
        $marketData = $this->_getPlayOrganizerTable()->get(array('id' => $goodData->organizer_id));
        $branchData = $this->_getPlayOrganizerTable()->fetchAll(array(
            'branch_id' => $goodData->organizer_id,
            'status > 0'
        ));

        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $vm = new ViewModel(array(
            'baoyoulist' => $baoyoulist,
            'gameInfo' => $gameInfo,
            'priceData' => $priceData,
            'useOrganizerData' => $useOrganizerData,
            'marketData' => $marketData,
            'branchData' => $branchData,
        ));

        return $vm;
    }

    public function saveGoodInfoAction()
    {


        $id = (int)$this->getPost('id');

        $shop_id = (int)$this->getPost('shop_id');
        $shop_name = trim($this->getPost('shop_name'));
        $price_id = (int)$this->getPost('pid');
        $total_num = $this->getPost('total_num', '');
        $start_time = strtotime($this->getPost('start_timel') . $this->getPost('start_timer')); //开始时间
        $end_time = strtotime($this->getPost('end_timel') . $this->getPost('end_timer')); //结束时间
        $organizer_id = (int)$this->getPost('organizer_id');

        $infoData = $this->_getPlayGameInfoTable()->get(array('id' => $id, 'status = 1'));
        if (!$infoData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));

        if (!$organizer_id || !$organizerData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '使用商家'));
        }

        if (!$shop_id || !$shop_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '关联游玩地'));
        }

        if ($start_time >= $end_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '时间不正确'));
        }

        if ($total_num < 0 or $total_num === '' or !is_numeric($total_num)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '接纳人数不正确'));
        }

        $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $price_id));

        if ($priceData->gid != $infoData->gid or !$priceData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if (!$priceData->contract_link_id) {//关联了合同的
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先加合同后再来'));
        }

        $organizerGame = $this->_getPlayOrganizerGameTable()->get(array('id' => $infoData->gid));
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $organizerGame->contract_id));

        if ($contractData->contracts_type == 1) {//包销限制数量
            $Contract = new Contract();
            $inventoryNum = $Contract->getContractLimitNum($priceData->contract_link_id, $id);

            if ($total_num > $inventoryNum) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该价格套系的数量不够了'));
            }
        }

        $placeCache = new PlaceCache();

        $used_status = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $id));

        if (!$used_status) {
            $this->_getPlayCodeUsedTable()->insert(array(
                'good_id' => $infoData->gid,
                'organizer_id' => $organizerData->id,
                'organizer_name' => $organizerData->name,
                'good_info_id' => $id,
            ));
        }

        if ($used_status && $used_status->organizer_id != $organizer_id) {
            $this->_getPlayCodeUsedTable()->update(array(
                'good_id' => $infoData->gid,
                'organizer_id' => $organizer_id,
                'organizer_name' => $organizerData->name
            ), array('id' => $used_status->id));
        }

        if (!$infoData->buy) {//没有售卖

            $infoNeWData = array(
                'total_num' => $total_num,
                'pid' => $price_id,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'shop_circle' => $placeCache->getPlaceCircle($shop_id),
            );

            $status = $this->_getPlayGameInfoTable()->update($infoNeWData, array('id' => $id));

            if ($status) {
                $Good = new Good();
                $Good->toRight($infoData->gid);
            }

            return $this->jsonResponsePage(array('status' => $status ? 1 : 0, 'message' => $status ? '成功' : '无修改'));
        }

        if ($total_num < $infoData->buy) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '不能小于购买数量'));
        }

        $infoNeWData = array(
            'total_num' => $total_num,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'shop_id' => $shop_id,
            'shop_name' => $shop_name,
            'shop_circle' => $placeCache->getPlaceCircle($shop_id),
        );

        $status = $this->_getPlayGameInfoTable()->update($infoNeWData, array('id' => $id));

        if ($status) {
            $Good = new Good();
            $Good->toRight($infoData->gid);
        }

        return $this->jsonResponsePage(array('status' => $status ? 1 : 0, 'message' => $status ? '成功' : '无修改'));

    }

    //商品套系 goodinfo
    public function saveGoodAction()
    { //新建保存套系
        $shop_id = (int)$this->getPost('shop_id');
        $shop_name = trim($this->getPost('shop_name'));
        $price_id = (int)$this->getPost('pid');
        $gid = (int)$this->getPost('gid');
        $organizer_id = (int)$this->getPost('organizer_id');
        $total_num = $this->getPost('total_num', '');

        $start_time = strtotime($this->getPost('start_timel') . $this->getPost('start_timer')); //开始时间
        $end_time = strtotime($this->getPost('end_timel') . $this->getPost('end_timer')); //结束时间

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));
        if (!$organizerData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '使用商家无'));
        }

        if (!$shop_id || !$shop_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '关联游玩地'));
        }

        if ($start_time >= $end_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '时间不正确'));
        }

        if ($total_num < 0 or $total_num === '' or !is_numeric($total_num)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '接纳人数不正确'));
        }

        $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $price_id));

        if ($priceData->gid != $gid) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));

        //同玩
        if ($goodData->g_buy) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '同玩商品只能有一个套系'));
        }

        //非合作
        if ($goodData->is_together == 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非合作商品不能添加套系'));
        }

        if (!$priceData->contract_link_id) {//关联了合同的
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先加合同后再来'));
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));

        if ($contractData->contracts_type == 1) {//包销限制数量
            $Contract = new Contract();
            $inventoryNum = $Contract->getContractLimitNum($priceData->contract_link_id, 0);

            if ($total_num > $inventoryNum) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该价格套系的数量不够了'));
            }
        }

        $placeCache = new PlaceCache();
        $account_money = $priceData->account_money ?: 0;

        $infoData = array(
            'total_num' => $total_num,
            'tid' => 0,
            'pid' => $price_id,
            'gid' => $gid,
            'price' => $priceData->price,
            'money' => $priceData->money,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'shop_id' => $shop_id,
            'shop_name' => $shop_name,
            'price_name' => $priceData->name,
            'shop_circle' => $placeCache->getPlaceCircle($shop_id),
            'account_money' => $account_money,
            'for_new' => $priceData->for_new,
            'limit_num' => $priceData->limit_num,
            'limit_low_num' => $priceData->limit_low_num,
            'qualified' => $priceData->qualified,
            'refund_time' => $priceData->refund_time,
            'up_time' => $priceData->up_time,
            'down_time' => $priceData->down_time,
            'remark' => $priceData->remark,
            'order_method' => $priceData->order_method,
            'contract_price_id' => $priceData->contract_link_id
        );

        $stu = $this->_getPlayGameInfoTable()->insert($infoData);

        if ($stu) { //新建使用商家

            $last_id = $this->_getPlayGameInfoTable()->getlastInsertValue();

            $this->_getPlayCodeUsedTable()->insert(array(
                'good_id' => $gid,
                'organizer_id' => $organizerData->id,
                'organizer_name' => $organizerData->name,
                'good_info_id' => $last_id,
            ));
            $Good = new Good();
            $Good->toRight($gid);
        }


        return $this->jsonResponsePage(array('status' => $stu, 'message' => ($stu ? '成功' : '失败'), 'gid' => $gid));
    }

    private function deleteGoodInfo($id)
    {
        $goodInfoData = $this->_getPlayGameInfoTable()->get(array('id' => $id));
        if (!$goodInfoData) {
            return $this->_Goto('失败');
        }
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $goodInfoData->gid));
        if ($goodData->is_together != 1) { //非合作
            return $this->_Goto('失败');
        }
        $status = $this->_getPlayGameInfoTable()->update(array('status' => 0), array('id' => $id));
        if ($status) {

            $Good = new Good();
            $Good->toRight($goodInfoData->gid);

            //删除相关的福利 返利 和 现金券
            $this->_getPlayWelfareTable()->delete(array(
                'good_info_id' => $id,
                'object_type' => 2,
                'object_id' => $goodInfoData->gid,
            ));
            GoodCache::setGameTags($goodInfoData->gid, $goodData->post_award);
        }

        return $status;
    }

    //删除套系
    public function deleteGoodInfoAction()
    {

        $id = (int)$this->getQuery('id');
        $status = $this->deleteGoodInfo($id);

        return $this->_Goto($status ? '成功' : '失败');
    }

    /**
     * 套系 end
     */


    /**
     * 价格系列 start
     */

    //添加修改价格套系
    public function priceAction()
    {
        $gid = (int)$this->getQuery('gid');
        $lid = (int)$this->getQuery('lid');
        $id = (int)$this->getQuery('id');
        if (!$id) {
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
            $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $lid, 'good_id' => $gid));
            if (!$goodData) {
                return $this->_Goto('非法操作');
            }
            if (!$linkData) {
                return $this->_Goto('请创建合同后再添加');
            }
            $priceData = $this->_getPlayGamePriceTable()->fetchLimit(0, 1, [],
                ['gid' => $goodData->id],
                ['id' => 'asc'])->current();

            $priceData = $priceData ?: null;
        } else {
            $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $id));
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $priceData->gid));
            if (!$priceData) {
                return $this->_Goto('非法操作1');
            }

            $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $priceData->contract_link_id));
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $vm = new ViewModel(array(
            'goodData' => $goodData,
            'linkData' => $linkData,
            'priceData' => $priceData,
            'baoyoulist' => $baoyoulist,
        ));

        return $vm;

    }

    //保存价格套系
    public function savePriceAction()
    {
        $id = (int)$this->getPost('id');
        $lid = (int)$this->getPost('lid');
        $gid = (int)$this->getPost('gid');

        $qualified = $this->getPost('qualified');
        $limit_num = (int)$this->getPost('limit_num');
        $limit_low_num = (int)$this->getPost('limit_low_num');//最少购买数量
        $excepted = (int)$this->getPost('excepted',0);//是否是特例不用现金券商品套系(特例商品)

        $up_time = strtotime($this->getPost('up_time') . $this->getPost('up_timel')); //开始售卖时间
        $down_time = strtotime($this->getPost('down_time') . $this->getPost('down_timel')); //结束售卖时间
        $refund_time = (int)strtotime($this->getPost('refund_time') . ' ' . $this->getPost('refund_timel')); //最后退款时间
        $remark = $this->getPost('remark'); //特别说明
        $order_method = $this->getPost('order_method'); //兑换方式
        $for_new = $this->getPost('for_new', 0);//是否新用户专享
        $single_income = floatval($this->getPost('single_income', 0));//销售员单份收益

        $insure_num_per_order = (int)$this->getPost('insure_num_per_order', 0);
        $insure_price = $insure_num_per_order ? $this->getPost('insure_price', 0) : 0;
        $insure_days = $insure_num_per_order ? $this->getPost('insure_days', 0) : 0;
        $integral = (int)$this->getPost('integral');
        $goods_sm = $this->getPost('goods_sm', 0);

        if (!$limit_num || $limit_num < $limit_low_num || !$limit_low_num) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '限制数量不正确'));
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));

        if (!$goodData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if ($up_time >= $down_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开始售卖时间 及 结束售卖时间 不对'));
        }

        if ($goodData->end_time < $down_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '下架时间需大于停止售卖时间'));
        }

        if ($refund_time < 596822400) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '停止退款时间请填写合理值'));
        }

        $name = trim($this->getPost('name'));
        //$total_num = (int)$this->getPost('total_num');
        $total_num = 0;

        if (!$name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '名称不要为空'));
        }

        /*if (!$total_num) {
            $this->jsonResponsePage(array('status' => 0, 'message' => '没有数量'));
        }*/

        if (!$order_method) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写兑换方式'));
        }

        if (!$remark) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写特别说明'));
        }

        $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $lid, 'good_id' => $gid));

        /*if ($single_income && $single_income < bcsub($linkData->price, $linkData->account_money, 2)) {
            $single_income = bcmul($single_income, 1, 2);
        } else {
            $single_income = 0;
        }*/

        if (!$id && !$linkData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先去创建合同'));
        }

        if ($id && !$linkData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '旧的不要修改了 就去创建合同吧'));
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $linkData->contract_id));

        if (!$contractData && !in_array($contractData->contracts_type, array(1, 3))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合同不存在'));
        }

        if (!$id) {
            $sameName = $this->_getPlayGamePriceTable()->get(array('name' => $name, 'gid' => $gid));
            if ($sameName) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已有该名称'));
            }
        } else {
            $sameName = $this->_getPlayGamePriceTable()->get(array('name' => $name, 'gid' => $gid, 'id != ?' => $id));
            if ($sameName) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已有该名称'));
            }

            $oldPriceData = $this->_getPlayGamePriceTable()->get(array('id' => $id, 'gid' => $gid));
            if (!$oldPriceData) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
        }

        if (!$id) {
            $data = array(
                'gid' => $gid,
                'name' => $name,
                'price' => $linkData->price,
                'money' => $linkData->money,
                'account_money' => $linkData->account_money,
                'contract_link_id' => $lid,
                'total_num' => $total_num,
                'qualified' => $qualified,
                'limit_num' => $limit_num,
                'limit_low_num' => $limit_low_num,
                'up_time' => $up_time,
                'down_time' => $down_time,
                'refund_time' => $refund_time,
                'remark' => $remark,
                'order_method' => $order_method,
                'for_new' => $for_new,
                'integral' => $integral,
                'insure_num_per_order' => $insure_num_per_order,
                'insure_price' => $insure_price,
                'insure_days' => $insure_days,
                'goods_sm' => $goods_sm,
                'single_income' => $single_income,
                'excepted' => $excepted,
            );

            $status = $this->_getPlayGamePriceTable()->insert($data);

            if ($status) {
                $this->setGoodtimes($gid);
            }

            return $this->jsonResponsePage(array(
                'status' => $status ? 1 : 0,
                'message' => $status ? '成功' : '失败',
                'gid' => $gid
            ));

        } else {

            $pgi_data = [
                'name' => $name,
                'qualified' => $qualified,
                'limit_num' => $limit_num,
                'limit_low_num' => $limit_low_num,
                'up_time' => $up_time,
                'down_time' => $down_time,
                'refund_time' => $refund_time,
                'remark' => $remark,
                'order_method' => $order_method,
                'total_num' => $total_num,
                'for_new' => $for_new,
                'integral' => $integral,
                'insure_num_per_order' => $insure_num_per_order,
                'insure_price' => $insure_price,
                'insure_days' => $insure_days,
                'goods_sm' => $goods_sm,
                'single_income' => $single_income,
                'excepted' => $excepted,
            ];

            //更新价格系列的 名称 数量  及 套系的系列名称
            $status = $this->_getPlayGamePriceTable()->update($pgi_data, array('id' => $id));

            $ginfo['refund_time'] = $refund_time;
            $ginfo['for_new'] = $for_new;
            $ginfo['limit_num'] = $limit_num;
            $ginfo['limit_low_num'] = $limit_low_num;
            $ginfo['qualified'] = $qualified;
            $ginfo['price_name'] = $name;
            $ginfo['up_time'] = $up_time;
            $ginfo['down_time'] = $down_time;
            $ginfo['remark'] = $remark;
            $ginfo['order_method'] = $order_method;
            //从套系中移到价格中的，更新是同步到info里面，以后看是否还需要这样
            $ginfo['integral'] = $integral;
            $ginfo['insure_num_per_order'] = $insure_num_per_order;
            $ginfo['insure_price'] = $insure_price;
            $ginfo['insure_days'] = $insure_days;
            $ginfo['goods_sm'] = $goods_sm;
            $ginfo['excepted'] = $excepted;
            $this->_getPlayGameInfoTable()->update($ginfo, array('pid' => $id, 'gid' => $gid));
            if ($status) {
                $this->setGoodtimes($gid);
                $this->setQuaNew($gid);
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功', 'gid' => $gid));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }

    }

    /**
     * $gid 商品id
     * 根据套系设置商品时间
     */
    private function setGoodtimes($gid)
    {
        $data = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [], ['status > ?' => 0, 'gid' => $gid],
            ['up_time' => 'asc'])->current();
        $datas['up_time'] = $data->up_time;//开始售卖时间
        $data = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [], ['status > ?' => 0, 'gid' => $gid],
            ['down_time' => 'desc'])->current();
        $datas['down_time'] = $data->down_time;//停止售卖时间
        $data = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [], ['status > ?' => 0, 'gid' => $gid],
            ['refund_time' => 'desc'])->current();
        $datas['refund_time'] = $data->refund_time;//停止退款时间
        $this->_getPlayOrganizerGameTable()->update($datas, ['id' => $gid]);
    }

    private function setQuaNew($gid)
    {
        $data = $this->_getPlayGamePriceTable()->fetchLimit(0, 1, [], ['qualified' => 2, 'gid' => $gid])->current();
        if ($data) {
            $this->_getPlayOrganizerGameTable()->update(['qualified' => 2], ['id' => $gid]);
        } else {
            $this->_getPlayOrganizerGameTable()->update(['qualified' => 1], ['id' => $gid]);
        }
        //for_new
        $data = $this->_getPlayGamePriceTable()->fetchLimit(0, 1, [], ['for_new' => 1, 'gid' => $gid])->current();
        if ($data) {
            $this->_getPlayOrganizerGameTable()->update(['for_new' => 1], ['id' => $gid]);
        } else {
            $this->_getPlayOrganizerGameTable()->update(['for_new' => 2], ['id' => $gid]);
        }
    }

    //删除价格套系
    public function deletePriceAction()
    {
        $id = (int)$this->getQuery('id');
        $infoData = $this->_getPlayGameInfoTable()->fetchAll(array('pid' => $id, 'status > 0'));
        if ($infoData->count()) {
            return $this->_Goto('请先删除关联该价格的套系');
        }

        $status = $this->_getPlayGamePriceTable()->delete(array('id' => $id));
        if ($status) {
            return $this->_Goto('成功');
        } else {
            return $this->_Goto('失败');
        }
    }

    /**
     * 价格系列 end
     */


    //发布设置
    public function publishAction()
    {

        //todo 合作 与 非合作判断

        $type = $this->getQuery('status');
        $gid = (int)$this->getQuery('gid');

        $organizerData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));

        if (!in_array($type, array(0, 1)) || !$organizerData) {
            return $this->_Goto('非法炒作');
        }

        if ($type == 0) {//发布

            if (!$organizerData->editor_talk || !$organizerData->information) {
                return $this->_Goto('请将商品小玩说 注意事项完善');
            }

            if ($organizerData->is_together == 1) {
                $flag = $this->_getPlayGameInfoTable()->get(array('gid' => $gid, 'status' => 1));
                if (!$flag) {
                    return $this->_Goto('合作商品 请完善价格套系');
                }
            }

            //判断是否有返利未审核
            $wr = $this->_getPlayWelfareRebateTable()->fetchLimit(0,1,[],['gid'=>$gid,'from_type'=>1,'status' => 1])->current();
            $cash = $this->_getPlayWelfareCashTable()->fetchAll(['gid'=>$gid])->toArray();
            $cids = [];
            foreach($cash as $cid){
                $cids[] = $cid['cash_coupon_id'];
            }
            if($cids){
                $cashes = $this->_getCashCouponTable()->fetchAll(['id'=>$cids])->toArray();
            }
            foreach($cashes as $c){
                if($c['status']<1 or $c['is_close']==1){
                    $wc = 1;
                }
            }

            if($wr or $wc){
                return $this->_Goto('现金券或返利未审核，不能发布');
            }

            $status = $this->_getPlayOrganizerGameTable()->update(array('status' => 1), array('id' => $gid));
        }

        if ($type == 1) {//取消发布
            $status = $this->_getPlayOrganizerGameTable()->update(array('status' => 0), array('id' => $gid));
        }

        return $this->_Goto($status ? '成功' : '失败');

    }

    //同玩设置
    public function playAction()
    {
        $gid = (int)$this->getQuery('gid');
        $type = (int)$this->getQuery('type');

        if ($type == 1) {// 取消同玩
            $status = $this->_getPlayOrganizerGameTable()->update(array('g_buy' => 0), array('id' => $gid));

            return $this->_Goto($status ? '成功' : '失败');
        }

        if ($type == 2) { //设为同玩
            // 检查是否可以设为同玩
            $gameData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));


            if (($gameData->down_time - time()) < 3600 * 2) {
                return $this->_Goto('距离结束时间小于2小时, 不能设为同玩');

            }

            $gameInfoData = $this->_getPlayGameInfoTable()->fetchAll(array('status > ?' => 0, 'gid' => $gid));
            if (!($gameInfoData->count() == 1)) {
                return $this->_Goto('同玩的话 只能设置一个套系');
            }

            $welfare = $this->_getPlayWelfareTable()->get(array(
                'good_info_id' => $gameInfoData->current()->id,
                'status' => 2,
                'object_type' => 2
            ));
            if ($welfare) {
                return $this->_Goto('同玩商品不能设置 现金 返利奖励');
            }

            if ($gameInfoData->current()->integral) {
                return $this->_Goto('同玩商品不能要有积分');
            }

            //商品 无需分享 无需新用户 无需资格券
            if ($gameData->for_new == 1) {
                return $this->_Goto('同玩不能与新用户专享一起');
            }

            if ($gameData->share == 2) {
                return $this->_Goto('同玩不能与需分享一起');
            }

//            if ($gameData->qualified == 2) {
//                return $this->_Goto('同玩不能需要资格券');
//            }

            //判断下面的套系
            if ($gameInfoData->current()->qualified == 2) {
                return $this->_Goto('同玩不能需要资格券');
            }

            $g_price = $this->getPost('g_price');
            $g_limit = (int)$this->getPost('g_limit');
            //同玩价格不能高于非同玩价格
            if ($g_price > $gameInfoData->current()->price) {
                return $this->_Goto('同玩价格不能高于非同玩价格');
            }

            if ($g_limit < 2) {
                return $this->_Goto('同玩人数不能小于1');
            }

            if ($g_limit > ($gameInfoData->current()->total_num - $gameInfoData->current()->buy)) {
                return $this->_Goto('同玩人数不能大于剩余人数');
            }

            if (!$g_price || !$g_limit) {
                return $this->_Goto('请认真填写同玩人数及价格');
            }

            $data = array(
                'g_limit' => $g_limit,
                'g_price' => $g_price,
                'g_buy' => 1,
            );

            $award = (int)$this->getPost('award', 0); //是否同玩奖励
            $cash_coupon_id = (int)$this->getPost('cash_coupon_id'); //优惠券名称
            $g_set_name = $this->getPost('g_set_name');//同玩套系名称

            if (!$g_set_name) {
                return $this->_Goto('请认真填写同玩套系名称');
            }
            $data['g_set_name'] = $g_set_name;
            $data['low_price'] = $g_price;

            $status = $this->_getPlayOrganizerGameTable()->update($data, array('id' => $gid));


            if ($status && $award) {//设置同玩对团长的奖励
                $this->_getPlayWelfareTable()->delete(array(
                    'object_id' => $gid,
                    'good_info_id' => $gameInfoData->current()->id,
                    'status' => 2,
                    'object_type' => 3
                ));
                $cashCoupon = $this->_getCashCouponTable()->get(array('id' => $cash_coupon_id));
                $this->_getPlayWelfareTable()->insert(array(
                    'object_id' => $gid,
                    'object_type' => 3,
                    'good_info_id' => $gameInfoData->current()->id,
                    'welfare_type' => 3,
                    'give_time' => 1,
                    'welfare_link_id' => $cash_coupon_id,
                    'welfare_value' => $cashCoupon->price,
                    'status' => 2,
                ));

            }

            return $this->_Goto($status ? '成功' : '失败');
        }

        exit;

    }

    //评论奖励
    public function saveAwardAction()
    {
        $id = (int)$this->getPost('gid');
        $post_award = $this->getPost('post_award');
        if (!in_array($post_award, array(1, 2))) {
            return $this->_Goto('非法操作');
        }

        $status = $this->_getPlayOrganizerGameTable()->update(array('post_award' => $post_award), array('id' => $id));

        if ($status) {
            GoodCache::setGameTags($id, $post_award);
        }

        return $this->_Goto($status ? '成功' : '失败');

    }

    //添加套系时获取 游玩地名称
    public function getShopAction()
    {
        $k = $this->getQuery('k');
        if ($k) {
            $where = array(
                'shop_name like ?' => '%' . $k . '%',
                'shop_status > -1',
            );
            $data = $this->_getPlayShopTable()->fetchLimit(0, 20, array(), $where, array());
            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val->shop_id,
                        'name' => $val->shop_name,
                    );
                }
            }

            return $this->jsonResponsePage(array('status' => 0, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    //创建商品时获取合同里面的商品
    public function getGoodNameAction()
    {

        $k = $this->getQuery('k');
        if ($k) {
            $data = $this->_getPlayContractsTable()->get(array('contract_no' => trim($k)));

            if (!$data) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '未找到该合同'));
            }

            $goodData = $this->_getPlayContractLinkGoodTable()->fetchAll(array(
                'contract_id' => $data->id,
                'good_id' => 0,
                'status > 0'
            ));
            if (!$goodData->count()) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该合同未关联商品套系'));
            }

            $res = array();
            foreach ($goodData as $good) {
                $res[] = $good->good_name;
            }

            if (!count($res)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该合同商品已创建完'));
            }

            $r = '<select name="title">';
            $m = '<input type="hidden" name="contract_id"  value="' . $data->id . '">';
            foreach ($res as $k => $s) {
                $r .= '<option value="' . $s . '">' . $s . '</option>';
            }

            $r .= $m;
            $r .= '</select>';

            return $this->jsonResponsePage(array('status' => 1, 'message' => $r));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '未找到该合同'));
        }
    }

    //购买奖励积分倍数 修改
    public function saveBuyIntegralAction()
    {
        $buy_integral = (float)$this->getPost('buy_integral', 0);

        if ($buy_integral < 0) {
            return $this->_Goto('非法操作');
        }
        $id = (int)$this->getPost('id');

        $this->_getPlayOrganizerGameTable()->update(array('buy_integral' => $buy_integral), array('id' => $id));

        RedCache::del('D:gtags:' . $id);

        return $this->_Goto('成功');
    }

    //购买奖励积分倍数 修改
    public function saveCashShareAction()
    {
        $cash_share = $this->getPost('cash_share', 0);

        if ($cash_share < 0) {
            return $this->_Goto('非法操作');
        }
        $id = (int)$this->getPost('goodid');

        $this->_getPlayOrganizerGameTable()->update(array('cash_share' => $cash_share), array('id' => $id));

        RedCache::del('D:gtags:' . $id);

        return $this->_Goto('成功');
    }

    //修改备注信息
    public function updateMessageAction()
    {

        $order_sn = $this->getQuery('order_sn');
        $message = $this->getPost('message');

        $orderOther = $this->_getPlayOrderOtherDataTable()->get(array('order_sn' => $order_sn));

        if (!$orderOther) {
            return $this->_Goto('该订单不能修改 订单信息');
        }

        if (!$message) {
            return $this->_Goto('备注信息不能为空');
        }

        $this->_getPlayOrderOtherDataTable()->update(array('message' => $message), array('order_sn' => $order_sn));

        return $this->_Goto('成功');
    }

    //修改收货地址
    public function updateAddressAction()
    {

        $order_sn = $this->getQuery('order_sn');
        $address = $this->getPost('address');

        $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));

        if (!$orderData) {
            return $this->_Goto('该订单不能修改 订单信息');
        }

        if (!$address) {
            return $this->_Goto('不能为空');
        }

        $flag = $this->_getPlayOrderInfoTable()->update(array('buy_address' => $address),
            array('order_sn' => $order_sn));

        return $this->_Goto($flag ? '成功' : '失败');
    }

    //================= 使用时间 ===========================
    public function priceInfoAction()
    {
        $gid = (int)$this->getQuery('gid');
        $type = $this->getQuery('type');


        if (!$type) {
            $type = 'basis';
        }

        $goodData = null;

        if ($gid) {
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
        } else {
            return $this->_Goto('id参数不正确');
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));
        $contract_on = $contractData->contract_no;

        $page = (int)$this->getQuery('p', 1);
        $price_id = (int)$this->getQuery('price_id', 0);
        $pageSum = (int)$this->getQuery('pageSum', 10);

        $start = ($page - 1) * $pageSum;
        $start = ($start < 0) ? 0 : $start;

        $startl = $this->getQuery('time_startl');
        $startr = $this->getQuery('time_startr');

        $endl = $this->getQuery('time_endl');
        $endr = $this->getQuery('time_endr');

        $time_start = strtotime($startl . ($startr ?: ' 00:00')); //开始售卖时间
        $time_end = strtotime($endl . ($endr ?: ' 23:59:59')); //结束售卖时间

        $where = '';

        if ($price_id) {
            $where .= " and play_game_info.pid = {$price_id}";
        }

        if ($startl or $endr) {
            $where .= " and play_game_info.start_time >= {$time_start}";
            $where .= " and play_game_info.end_time <= {$time_end}";
        }

        //合同限制套系
        $contractLinkData = $this->_getPlayContractLinkPriceTable()->fetchAll(array(
            'good_id' => $gid,
            'status >= ?' => 1
        ));

        //价格系列
        $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $gid), ['id' => 'desc']);

        //套系
        //$goodInfoData = $this->_getPlayGameInfoTable()->getUsedOrganizer(0, 1000, array(), array('play_game_info.gid' => $gid, 'play_game_info.status > 0'));
        $good_info_sql = " SELECT
play_game_info.*,
play_code_used.organizer_name
From play_game_info
LEFT JOIN play_code_used ON (play_code_used.good_info_id = play_game_info.id )
WHERE play_game_info.gid = {$gid}{$where}
order by status desc, (start_time+3600*24 > unix_timestamp(now())) desc,start_time asc
LIMIT {$start}, {$pageSum}"; //AND play_game_info.status > 0

        $goodInfoData = $this->query($good_info_sql);

        $good_info_sql = " SELECT
count(*) as c
From play_game_info
LEFT JOIN play_code_used ON (play_code_used.good_info_id = play_game_info.id AND play_game_info.status > 0)
WHERE play_game_info.gid = {$gid}{$where}";

        $count = $this->query($good_info_sql)->current();

        $count = $count['c'];

        $url = '/wftadlogin/good/priceInfo';
        $paginator = new Paginator($page, $count, $pageSum, $url);

        //商家 与 分店
        $marketData = $this->_getPlayOrganizerTable()->get(array('id' => $goodData->organizer_id));
        $branchData = $this->_getPlayOrganizerTable()->fetchAll(array(
            'branch_id' => $goodData->organizer_id,
            'status > 0'
        ));

        //套系使用商家
        //$infoUsedData = $this->_getPlayCodeUsedTable()->fetchAll(array('good_id' => $gid));

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));

        if ($contractData && !in_array($contractData->contracts_type, array(1, 3))) {
            return $this->_Goto('该合同类型不存在');
        }

        $tip = '';
        if ($contractData) {
            if ($contractData->contracts_type == 1) {
                $inventoryData = $this->_getPlayInventoryTable()->fetchAll(array(
                    'good_id' => $gid,
                    'contract_id' => $goodData->contract_id,
                    'inventory_status > ?' => 0
                ));
                foreach ($inventoryData as $inventory) {
                    $tip = $tip + $inventory['purchase_number'];
                }
            }
        }

        if ($tip) {
            $tip = '商品可售数量' . $tip;
        }


        $vm = new ViewModel(
            array(
                'baoyoulist' => $baoyoulist,
                'contractLinkData' => $contractLinkData, //合同限制的
                'goodData' => $goodData, //商品数据
                'priceData' => $priceData, //价格系列
                'goodInfoData' => $goodInfoData, //套系
                'marketData' => $marketData,//商家
                'branchData' => $branchData, //分店
                'tip' => $tip,
                'startl' => $startl,
                'startr' => $startr,
                'endl' => $endl,
                'endr' => $endr,
                'price_id' => $price_id,
                'pageSum' => $pageSum,
                'pageData' => $paginator->getHtml(),
                'contract_on' => $contract_on, //套系使用商家
            )
        );

        $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        setcookie('url', $url, time() + 86400);

        return $vm;
    }

    /**
     * 添加价格套系
     */
    public function newPriceInfoAction()
    {
        $gid = (int)$this->getQuery('gid');
        $lid = (int)$this->getQuery('lid');
        $id = (int)$this->getQuery('id');
        if (!$id) {
            //开始结束售卖时间、退款规则设置、提前预约时间、特别说明、兑换方式
            $priceData = $this->_getPlayGamePriceTable()->fetchLimit(0, 1, [],
                array('gid' => $gid),
                ['id' => 'asc'])->current();

            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
            $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $lid, 'good_id' => $gid));
            if (!$goodData) {
                return $this->_Goto('非法操作');
            }
            if (!$linkData) {
                return $this->_Goto('请创建合同后再添加');
            }

        } else {
            $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $id));
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $priceData->gid));
            if (!$priceData) {
                return $this->_Goto('非法操作1');
            }

            $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $priceData->contract_link_id));
        }

        //获取保险列表
        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $vm = new ViewModel(array(
            'goodData' => $goodData,
            'linkData' => $linkData,
            'priceData' => $priceData,
            'baoyoulist' => $baoyoulist,
        ));

        return $vm;
    }

    /**
     * 添加价格套系
     */
    public function newPlaceInfoAction()
    {
        $id = (int)$this->getQuery('id', 0);
        $gid = (int)$this->getQuery('gid');
        $organizer_id = 0;
        if ($id) {
            $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $id, 'status = 1'));
            $used_status = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $id));
            if ($used_status) {
                $organizer_id = $used_status->organizer_id;
            }
            $gid = $gameInfo->gid;
            if ($gameInfo) {
                $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
                if (!$goodData) {
                    return $this->_Goto('非法操作-id无效');
                }
            } else {
                return $this->_Goto('非法操作-id无效');
            }

        } elseif ($gid) {

            $gameInfo = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [], array('gid' => $gid, 'status' => 1),
                ['id' => 'asc'])->current();

            $gameInfo = $gameInfo ?: null;

            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
            if (!$goodData) {
                return $this->_Goto('非法操作-id无效');
            }
            if (!$goodData) {
                return $this->_Goto('非法操作-id无效');
            }
        } else {
            return $this->_Goto('非法操作-id无效');
        }

        //价格系列
        $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $gid), ['id' => 'desc']);

        //商家与分店
        $shop = $this->_getAdapter()->query('select * from play_organizer where id = ? or branch_id = ?',
            [$goodData->organizer_id, $goodData->organizer_id]);

        $vm = new ViewModel(array(
            'goodData' => $goodData,
            'priceData' => $priceData,
            'shop' => $shop,
            'gameInfo' => $gameInfo,
            'id' => $id,
            'organizer_id' => $organizer_id ?: 0,
        ));

        return $vm;
    }

    //保存价格套系
    public function newsavePriceAction()
    {
        $id = (int)$this->getPost('id');
        $lid = (int)$this->getPost('lid');
        $gid = (int)$this->getPost('gid');

        $excepted = (int)$this->getPost('excepted',0);//是否是特例不用现金券商品套系（特例商品）

        $qualified = $this->getPost('qualified');
        $limit_num = (int)$this->getPost('limit_num');
        $limit_low_num = (int)$this->getPost('limit_low_num');//最少购买数量

        $up_time = strtotime($this->getPost('up_time') . $this->getPost('up_timel')); //开始售卖时间
        $down_time = strtotime($this->getPost('down_time') . $this->getPost('down_timel')); //结束售卖时间

        $refund_time = (int)strtotime($this->getPost('refund_time') . ' ' . $this->getPost('refund_timel')); //最后退款时间
        $back_rule = (int)$this->getPost('back_rule', 0);
        $refund_before_day = (int)$this->getPost('refund_before_day', 0);
        $refund_before_time = (int)strtotime($this->getPost('refund_before_time', 0));

        $book_hours = (int)$this->getPost('book_hours', 0);
        $book_time = (int)strtotime($this->getPost('book_time', 0));
        $single_income = floatval($this->getPost('single_income', 0));//销售员单份收益

        $insure_num_per_order = (int)$this->getPost('insure_num_per_order', 0);
        $insure_price = $insure_num_per_order ? $this->getPost('insure_price', 0) : 0;
        $insure_days = $insure_num_per_order ? $this->getPost('insure_days', 0) : 0;

        $integral = (int)$this->getPost('integral');

        $goods_sm = $this->getPost('goods_sm', 0);

        $remark = $this->getPost('remark'); //特别说明
        $order_method = $this->getPost('order_method'); //兑换方式
        $for_new = $this->getPost('for_new', 0);//是否新用户专享

        if (!$limit_num || $limit_num < $limit_low_num || !$limit_low_num) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '限制数量不正确'));
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));

        if (!$goodData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if ($up_time >= $down_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开始售卖时间 及 结束售卖时间 不对'));
        }

        if ($refund_time < 596822400) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '停止退款时间请填写合理值'));
        }

        $name = trim($this->getPost('name'));

        if (!$name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '名称不要为空'));
        }

        if (!$order_method) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写兑换方式'));
        }

        if (!$remark) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请填写特别说明'));
        }

        $linkData = $this->_getPlayContractLinkPriceTable()->get(array('id' => $lid, 'good_id' => $gid));

        if (!$id && !$linkData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先去创建合同'));
        }

        if ($id && !$linkData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '旧的不要修改了 就去创建合同吧'));
        }

        /*if ($single_income && $single_income < bcsub($linkData->price, $linkData->account_money, 2)) {
            $single_income = bcmul($single_income, 1, 2);
        } else {
            $single_income = 0;
        }*/

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $linkData->contract_id));

        if (!$contractData && !in_array($contractData->contracts_type, array(1, 3))) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '合同不存在'));
        }

        if (!$id) {
            $sameName = $this->_getPlayGamePriceTable()->get(array('name' => $name, 'gid' => $gid));
            if ($sameName) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已有该名称'));
            }
        } else {
            $sameName = $this->_getPlayGamePriceTable()->get(array('name' => $name, 'gid' => $gid, 'id != ?' => $id));
            if ($sameName) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已有该名称'));
            }

            $oldPriceData = $this->_getPlayGamePriceTable()->get(array('id' => $id, 'gid' => $gid));
            if (!$oldPriceData) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
        }

        if (!$id) {
            $data = array(
                'gid' => $gid,
                'name' => $name,
                'price' => $linkData->price,
                'money' => $linkData->money,
                'account_money' => $linkData->account_money,
                'contract_link_id' => $lid,
                'qualified' => $qualified,
                'limit_num' => $limit_num,
                'limit_low_num' => $limit_low_num,
                'up_time' => $up_time,
                'down_time' => $down_time,
                'refund_time' => $refund_time,
                'remark' => $remark,
                'order_method' => $order_method,
                'for_new' => $for_new,
                'integral' => $integral,
                'insure_num_per_order' => $insure_num_per_order,
                'insure_price' => $insure_price,
                'insure_days' => $insure_days,
                'goods_sm' => $goods_sm,
                'excepted' => $excepted,
                'back_rule' => $back_rule,
                'single_income' => $single_income,
                'refund_before_day' => $refund_before_day,
                'refund_before_time' => $refund_before_time,
                'book_hours' => $book_hours,
                'book_time' => $book_time,
            );

            $status = $this->_getPlayGamePriceTable()->insert($data);

            if ($status) {
                $this->setGoodtimes($gid);
            }

            return $this->jsonResponsePage(array(
                'status' => $status ? 1 : 0,
                'message' => $status ? '成功' : '失败',
                'gid' => $gid
            ));

        } else {

            $pgi_data = [
                'name' => $name,
                'qualified' => $qualified,
                'limit_num' => $limit_num,
                'limit_low_num' => $limit_low_num,
                'up_time' => $up_time,
                'down_time' => $down_time,
                'refund_time' => $refund_time,
                'remark' => $remark,
                'order_method' => $order_method,
                'for_new' => $for_new,
                'integral' => $integral,
                'insure_num_per_order' => $insure_num_per_order,
                'insure_price' => $insure_price,
                'insure_days' => $insure_days,
                'goods_sm' => $goods_sm,
                'back_rule' => $back_rule,
                'single_income' => $single_income,
                'refund_before_day' => $refund_before_day,
                'refund_before_time' => $refund_before_time,
                'book_hours' => $book_hours,
                'book_time' => $book_time,
                'excepted' => $excepted,
            ];

            //更新价格系列的 名称 数量  及 套系的系列名称
            $status = $this->_getPlayGamePriceTable()->update($pgi_data, array('id' => $id));

            $ginfo['refund_time'] = $refund_time;
            $ginfo['for_new'] = $for_new;
            $ginfo['limit_num'] = $limit_num;
            $ginfo['limit_low_num'] = $limit_low_num;
            $ginfo['qualified'] = $qualified;
            $ginfo['price_name'] = $name;
            $ginfo['up_time'] = $up_time;
            $ginfo['down_time'] = $down_time;
            $ginfo['remark'] = $remark;
            $ginfo['order_method'] = $order_method;
            $ginfo['excepted'] = $excepted;
            $ginfo['insure_num_per_order'] = $insure_num_per_order;
            $ginfo['insure_price'] = $insure_price;
            $ginfo['insure_days'] = $insure_days;
            $ginfo['integral'] = $integral;
            $ginfo['goods_sm'] = $goods_sm;
            $this->_getPlayGameInfoTable()->update($ginfo, array('pid' => $id, 'gid' => $gid));
            if ($status) {
                $this->setGoodtimes($gid);
                $this->setQuaNew($gid);
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功', 'gid' => $gid));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
            }
        }
    }

    /**
     * 更新套系
     * @return \Zend\View\Model\JsonModel
     */
    public function newsaveGoodInfoAction()
    {
        $id = (int)$this->getPost('id');

        $price_id = (int)$this->getPost('pid');
        $total_num = $this->getPost('total_num', '');

        $organizer_id = (int)$this->getPost('organizer_id');

        $infoData = $this->_getPlayGameInfoTable()->get(array('id' => $id));

        $money = $this->getPost('money', '');
        $price = $this->getPost('price', '');
        $account_money = $this->getPost('account_money', '');

        if ($money === '' or $price === '' or $account_money === '') {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '价格必填'));
        }

        if (!is_numeric($money) or !is_numeric($price) === '' or !is_numeric($account_money)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '价格不正确'));
        }

        $money = (float)$money;
        $price = (float)$price;
        $account_money = (float)$account_money;

        if (!$infoData or $infoData->status < 1) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));

        if (!$organizer_id || !$organizerData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '使用商家不存在'));
        }

        if ($total_num < 0 or $total_num === '' or !is_numeric($total_num)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '接纳人数不正确'));
        }

        $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $price_id));

        if ($priceData->gid != $infoData->gid) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if ($total_num < $infoData->buy) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '不能小于购买数量'));
        }

        if (!$priceData->contract_link_id) {//关联了合同的
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先加合同后再来'));
        }

        $organizerGame = $this->_getPlayOrganizerGameTable()->get(array('id' => $infoData->gid));

        $data['money'] = $this->getPost('money', '');
        if ($data['money'] === '') {
            unset($data['money']);
        }
        $data['contract_link_id'] = $priceData->contract_link_id;
        $data['price'] = $this->getPost('price', '');
        if ($data['price'] === '') {
            unset($data['price']);
        }
        $data['account_money'] = $this->getPost('account_money', '');
        if ($data['account_money'] === '') {
            unset($data['account_money']);
        }
        $data['good_id'] = (int)$infoData->gid;
        $data['contract_id'] = (int)$organizerGame->contract_id;
        $data['total_num'] = $total_num;
        //更新价格方案
        $lastid = $this->updatePriceOrigin($data);
        $contractData = $this->_getPlayContractsTable()->get(array('id' => $organizerGame->contract_id));

        //判断价格是否有变动
        if ((float)$infoData->price === $price and (float)$infoData->money === $money and (float)$infoData->account_money === $account_money) {
            $infoNeWData = array(
                'total_num' => $total_num,
            );

            if ($contractData->contracts_type == 1) {//包销限制数量
                $Contract = new Contract();
                $inventoryNum = $Contract->getContractLimitNum($priceData->contract_link_id, $id);

                if ($total_num > $inventoryNum) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '该价格套系的数量不够了'));
                }
            }

            $status = $this->_getPlayGameInfoTable()->update($infoNeWData, array('id' => $id));

            if ($status) {
                $Good = new Good();
                $Good->toRight($infoData->gid);
            }
        } else {
            $hideninfo = $this->_getPlayGameInfoTable()->get(array('id' => $id));
            $infoNeWData = (array)$hideninfo;
            unset($infoNeWData['id']);
            $infoNeWData['price'] = $price;
            $infoNeWData['money'] = $money;
            $infoNeWData['account_money'] = $account_money;
            $infoNeWData['buy'] = 0;
            if ($lastid) {
                $infoNeWData['contract_price_id'] = $lastid;
            }
            if ((int)$total_num === (int)$hideninfo->total_num) {
                $infoNeWData['total_num'] = $hideninfo->total_num - $hideninfo->buy;
            } else {
                $infoNeWData['total_num'] = $total_num;
            }

            if ($contractData->contracts_type == 1) {//包销限制数量
                $Contract = new Contract();
                $inventoryNum = $Contract->getContractLimitNum($priceData->contract_link_id, $id);

                if ($infoNeWData['total_num'] > $inventoryNum) {
                    return $this->jsonResponsePage(array('status' => 0, 'message' => '该价格套系的数量不够了'));
                }
            }

            $this->deleteGoodInfo($id);
            $status = $this->_getPlayGameInfoTable()->insert($infoNeWData);
            $id = $this->_getPlayGameInfoTable()->getlastInsertValue();
            $this->_getPlayCodeUsedTable()->insert(array(
                'good_id' => $infoData->gid,
                'organizer_id' => $organizerData->id,
                'organizer_name' => $organizerData->name,
                'good_info_id' => $id,
            ));

            if ($status) {
                $Good = new Good();
                $Good->toRight($infoData->gid);
            }
        }

        return $this->jsonResponsePage(array('status' => $status ? 1 : 0, 'message' => $status ? '成功' : '无修改'));
    }

    //商品套系 goodinfo
    /**
     * 添加套系
     * @return \Zend\View\Model\JsonModel
     */
    public function newsaveGoodAction()
    { //新建保存套系
        $shop_id = (int)$this->getPost('shop_id');
        $shop_name = trim($this->getPost('shop_name'));
        $price_id = (int)$this->getPost('pid');
        $gid = (int)$this->getPost('gid');
        $organizer_id = (int)$this->getPost('organizer_id');//验证使用商家
        $total_num = $this->getPost('total_num', '');

        $start_time = $start_time_temp = strtotime($this->getPost('time_startl') . $this->getPost('time_startr',
                '00:00')); //开始时间
        $end_time = $end_time_temp = strtotime($this->getPost('time_endl') . $this->getPost('time_endr',
                '00:00')); //结束时间

        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $organizer_id));
        if (!$organizerData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '使用商家无'));
        }

        if (!$shop_id || !$shop_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '关联游玩地'));
        }

        if ($start_time > $end_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '时间不正确'));
        }

        if ($total_num < 0 or $total_num === '' or !is_numeric($total_num)) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '接纳人数不正确'));
        }

        $priceData = $this->_getPlayGamePriceTable()->get(array('id' => $price_id));

        if ($priceData->gid != $gid) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        //同步老版本套系
        $integral = $priceData->integral;
        $insure_num_per_order = $priceData->insure_num_per_order;
        $insure_price = $priceData->insure_price;
        $insure_days = $priceData->insure_days;
        $goods_sm = $priceData->goods_sm;

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));

        //同玩
        if ($goodData->g_buy) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '同玩商品只能有一个套系'));
        }

        //非合作
        if ($goodData->is_together == 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非合作商品不能添加套系'));
        }

        if (!$priceData->contract_link_id) {//关联了合同的
            return $this->jsonResponsePage(array('status' => 0, 'message' => '请先加合同后再来'));
        }

        $contractData = $this->_getPlayContractsTable()->get(array('id' => $goodData->contract_id));

        if ($contractData->contracts_type == 1) {//包销限制数量
            $Contract = new Contract();
            $inventoryNum = $Contract->getContractLimitNum($priceData->contract_link_id, 0);
            $days = 1;
            if ($goodData->need_use_time == 2) {//预约时间
                while (1) {
                    $start_time_temp += 3600 * 24;

                    if ($start_time_temp > $end_time_temp) {
                        break;
                    }
                    $days++;
                }

            }

            if ($total_num * $days > $inventoryNum) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '该价格套系的数量不够了'));
            }
        }

        $placeCache = new PlaceCache();
        $account_money = $priceData->account_money ?: 0;

        $infoData = array(
            'total_num' => $total_num,
            'tid' => 0,
            'pid' => $price_id,
            'gid' => $gid,
            'price' => $priceData->price,
            'money' => $priceData->money,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'shop_id' => $shop_id,
            'shop_name' => $shop_name,
            'price_name' => $priceData->name,
            'shop_circle' => $placeCache->getPlaceCircle($shop_id),
            'integral' => $integral,
            'account_money' => $account_money,
            'insure_num_per_order' => $insure_num_per_order,
            'insure_price' => $insure_price,
            'insure_days' => $insure_days,
            'goods_sm' => $goods_sm,
            'for_new' => $priceData->for_new,
            'limit_num' => $priceData->limit_num,
            'limit_low_num' => $priceData->limit_low_num,
            'qualified' => $priceData->qualified,
            'refund_time' => $priceData->refund_time,
            'up_time' => $priceData->up_time,
            'down_time' => $priceData->down_time,
            'remark' => $priceData->remark,
            'order_method' => $priceData->order_method,
            'contract_price_id' => $priceData->contract_link_id
        );

        if ($goodData->need_use_time == 1) {//非预约时间
            $stu = $this->_getPlayGameInfoTable()->insert($infoData);

            if ($stu) { //新建使用商家

                $last_id = $this->_getPlayGameInfoTable()->getlastInsertValue();

                $this->_getPlayCodeUsedTable()->insert(array(
                    'good_id' => $gid,
                    'organizer_id' => $organizerData->id,
                    'organizer_name' => $organizerData->name,
                    'good_info_id' => $last_id,
                ));
                $Good = new Good();
                $Good->toRight($gid);
            }
        } elseif ($goodData->need_use_time == 2) {//预约时间
            $i = 0;
            $log_dates = [];
            while (true) {
                $infoData['start_time'] = $start_time;
                $infoData['end_time'] = $start_time + 3600 * 24 - 1;

                $exists = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [],
                    [
                        'gid' => $gid,
                        'pid' => $price_id,
                        'start_time' => $start_time,
                        'end_time' => $infoData['end_time'],
                        'status' => 1
                    ])->current();

                if (!$exists) {
                    $stu = $this->_getPlayGameInfoTable()->insert($infoData);

                    if ($stu) { //新建使用商家
                        $last_id = $this->_getPlayGameInfoTable()->getlastInsertValue();
                        $this->_getPlayCodeUsedTable()->insert(array(
                            'good_id' => $gid,
                            'organizer_id' => $organizerData->id,
                            'organizer_name' => $organizerData->name,
                            'good_info_id' => $last_id,
                        ));
                    }
                } else {
                    $log_dates[] = date('Y-m-d', $start_time);
                }

                if ($start_time >= $end_time) {
                    break;
                }
                $start_time += 3600 * 24;
                $i++;
                if ($i > 99) {
                    break;
                }
            }
            $Good = new Good();
            $Good->toRight($gid);

        }
        $status = 1;
        if ($log_dates) {
            $log_dates = implode(',', $log_dates) . '日期重复，已跳过添加';
        }

        return $this->jsonResponsePage(array(
            'status' => $status,
            'message' => ($status ? '成功' : '失败'),
            'gid' => $gid,
            'log_days' => $log_dates ?: ''
        ));
    }

    /**
     *
     */
    private function updatePriceInfo($id, $up_arr)
    {

        $this->_getPlayGameInfoTable()->update($up_arr, ['pid' => $id]);

    }

    //时间地点批量修改
    public function batchingPriceAction()
    {
        $data = [];
        $data['money'] = $final_money = trim($this->getPost('money', ''));
        if ($data['money'] === '' or !is_numeric($data['money'])) {
            unset($data['money']);
        } else {
            $data['money'] = $final_money = (float)$final_money;
        }
        $data['price'] = $final_price = trim($this->getPost('price', ''));
        if ($data['price'] === '' or !is_numeric($data['price'])) {
            unset($data['price']);
        } else {
            $data['price'] = $final_price = (float)$final_price;
        }
        $data['account_money'] = $final_account_money = trim($this->getPost('account_money', ''));
        if ($data['account_money'] === '' or !is_numeric($data['account_money'])) {
            unset($data['account_money']);
        } else {
            $data['account_money'] = $final_account_money = (float)$final_account_money;
        }
        $data['total_num'] = $final_total_num = trim($this->getPost('total_num', ''));
        if ($data['total_num'] === '' or !is_numeric($data['total_num'])) {
            unset($data['total_num']);
        } else {
            $data['total_num'] = $final_total_num = (int)$final_total_num;
        }

        if (!array_key_exists('money', $data) and !array_key_exists('price', $data) and !array_key_exists('account_money',
                $data) and !array_key_exists('total_num', $data)
        ) {
            return $this->_Goto('非法操作');
        }

        $id = $this->getPost('ids', 0);
        $data['good_id'] = $gid = (int)$this->getPost('vgid', 0);
        $data['contract_id'] = $f_contract_id = (int)$this->getPost('contractid', 0);

        $id = explode(',', $id);
        $id = array_filter($id);
        $cpid = $ids = $a = [];
        $id_in_contract = [];
        foreach ($id as $i) {
            $arr = explode('-', $i);
            $i = $arr[0];//套系
            $c = $arr[1];//价格方案
            if (!is_numeric($i) or !is_numeric($c)) {
                return $this->_Goto('非法操作-id无效');
            }
            $ids[] = $i;//套系id

            if (!in_array((int)$c, $cpid, true)) {
                $cpid[] = (int)$c;
            }
            $id_in_contract[(int)$c][] = $i;//记录同一价格方案的不同时间地点
        }

        if (count($ids) < 1) {
            return $this->_Goto('非法操作-id无效');
        }

        if (!$a and array_key_exists('total_num', $data)) {//如果对接纳人有修改
            $inid = implode(',', $ids);
            if (count($ids) == 1) {
                $inid = $ids[0];
            } else {
                $inid = str_replace("\\'", '', $inid);
            }
            $sql = "select * from play_game_info where buy > ? and id in ({$inid}) limit 1";
            $apt = $this->_getAdapter();
            $result = $apt->query($sql, [(int)$data['total_num']])->current();

            if ($result) {
                return $this->_Goto('该价格套系的数量不能小于已经购买数量');
            }
            $a = 1;
        }

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $data['good_id']));

        //判断库存是否超
        $inventoryNum = 0;
        if ($data['total_num']) {
            $Contract = new Contract();
            $inventoryNum = $Contract->getContractLeave($ids, $data['good_id'], $data['total_num'],$final_price,$final_money,$final_account_money);
        }
        if ($inventoryNum === 1) {
            return $this->_Goto('该价格套系的数量不够了');
        }

        foreach ($cpid as $cp) {
            $data['contract_link_id'] = $cp;
            $data['good_id'] = $gid;
            $lastid = $this->updatePriceOrigin($data);

            if ($lastid) {
                $data['contract_price_id'] = $lastid;
            }

            if (!$data['account_money'] or (int)$goodData->contracts_type === 1) {
                unset($data['account_money']);
            }

            unset($data['good_id'], $data['contract_id'], $data['contract_link_id']);
            if ($data) {
                if ($id_in_contract[$cp]) {
                    foreach ($id_in_contract[$cp] as $info) {
                        $infoData = $this->_getPlayGameInfoTable()->get(array('id' => $info));
                        if ($data['total_num'] === '' or !array_key_exists('total_num', $data)) {
                            $data['total_num'] = $infoData->total_num;
                        }
                        if ($data['price'] === '' or !array_key_exists('price', $data)) {
                            $data['price'] = $infoData->price;
                        }
                        if ($data['money'] === '' or !array_key_exists('money', $data)) {
                            $data['money'] = $infoData->money;
                        }
                        if ($data['account_money'] === '' or !array_key_exists('account_money', $data)) {
                            $data['account_money'] = $infoData->account_money;
                        }
                        //判断价格是否有变动

                        if ((float)$infoData->price === (float)$data['price']
                            and (float)$infoData->money === (float)$data['money']
                            and (float)$infoData->account_money === (float)$data['account_money']
                        ) {
                            if ($data['total_num'] !== '') {
                                $infoNeWData['total_num'] = (int)$data['total_num'];
                                $this->_getPlayGameInfoTable()->update($infoNeWData, array('id' => $info));
                            }
                        } else {
                            $infoNeWData = (array)$this->_getPlayGameInfoTable()->get(array('id' => $info));
                            $infoNeWData['price'] = $data['price'];
                            $infoNeWData['money'] = $data['money'];
                            $infoNeWData['account_money'] = $data['account_money'];

                            if ($lastid) {
                                $infoNeWData['contract_price_id'] = $lastid;
                            }
                            if ((int)$data['total_num'] === (int)$infoNeWData['total_num']) {
                                $infoNeWData['total_num'] = $data['total_num'] - $infoNeWData['buy'];
                            } else {
                                $infoNeWData['total_num'] = $data['total_num'];
                            }

                            unset($infoNeWData['id']);
                            $used_status = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $info));
                            $this->deleteGoodInfo($info);
                            $infoNeWData['buy'] = 0;
                            $this->_getPlayGameInfoTable()->insert($infoNeWData);
                            $id = $this->_getPlayGameInfoTable()->getlastInsertValue();
                            $this->_getPlayCodeUsedTable()->insert(array(
                                'good_id' => $infoData->gid,
                                'organizer_id' => $used_status->organizer_id,
                                'organizer_name' => $used_status->organizer_name,
                                'good_info_id' => $id,
                            ));
                        }
                        $data['money'] = $final_money;
                        $data['price'] = $final_price;
                        $data['account_money'] = $final_account_money;
                        $data['total_num'] = $final_total_num;
                    }
                }
                $Good = new Good();
                $Good->toRight($gid);
            }
            $data['money'] = $final_money;
            $data['price'] = $final_price;
            $data['account_money'] = $final_account_money;
            $data['total_num'] = $final_total_num;
        }

        if($goodData->need_use_time == 2 and $goodData->account_type == 1){//如果是批量修改，且是包销，作为多人操作可能出现的数量错误
            $inven = $this->_getPlayInventoryTable()->fetchAll(['good_id' => $gid],[],500)->toArray();
            foreach($inven as $i){
                $pclpt = $this->_getPlayContractLinkPriceTable()->fetchAll(['inventory_id'=>$i['id']],[],500)->toArray();
                if($pclpt){
                    $pid = [];
                    foreach($pclpt as $p){
                        $pid[] = $p['id'];
                    }
                    $infos = $this->_getPlayGameInfoTable()->fetchAll(['contract_price_id'=>$pid]);
                    $total = 0;
                    foreach($infos as $is){
                        if($is['status']){
                            $total += $is['total_num'];
                        }else{
                            $total += $is['buy'];
                        }
                        if($total > $i['purchase_number']){
                            return $this->_Goto('接纳人数超出了库存量，请修改加纳人数');
                        }
                    }
                }
            }
        }

        return $this->_Goto('成功');
    }


    private function batchingdeleteGoodInfo($id)
    {

        $goodInfoData = $this->_getPlayGameInfoTable()->get(array('id' => $id));
        if (!$goodInfoData) {
            return false;
        }
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $goodInfoData->gid));
        if ($goodData->is_together != 1) { //非合作
            return false;
        }
        $status = $this->_getPlayGameInfoTable()->update(array('status' => 0), array('id' => $id));
        if ($status) {

            $Good = new Good();
            $Good->toRight($goodInfoData->gid);

            //删除相关的福利 返利 和 现金券
            $this->_getPlayWelfareTable()->delete(array(
                'good_info_id' => $id,
                'object_type' => 2,
                'object_id' => $goodInfoData->gid,
            ));
            GoodCache::setGameTags($goodInfoData->gid, $goodData->post_award);
        }
    }

    public function batchingdeleteGoodInfoAction()
    {
        $id = $this->getQuery('id', 0);
        $id = explode(',', $id);
        $id = array_filter($id);
        foreach ($id as $i) {
            $i = substr($i, 0, strpos($i, '-'));
            if (!is_numeric($i)) {
                return $this->_Goto('非法操作-id无效');
            }
        }
        if (count($id) < 1) {
            return $this->_Goto('非法操作-id无效');
        }

        foreach ($id as $i) {
            $this->batchingdeleteGoodInfo($i);
        }

        return $this->_Goto('成功');
    }

    /**
     * 更新价格方案
     * @param $data
     * @id 一个或多个套系id
     * @return 关联的价格方案
     */
    private function updatePriceOrigin($data)
    {
        $pre_data = $this->_getPlayContractLinkPriceTable()->get([
            'id' => $data['contract_link_id']
        ]);

        if ($data['money'] !== '' and array_key_exists('money', $data)) {
            $where['money'] = sprintf("%.2f", $data['money']);
        } else {
            $where['money'] = $pre_data->money;
        }

        if ($data['price'] !== '' and array_key_exists('price', $data)) {
            $where['price'] = sprintf("%.2f", $data['price']);
        } else {
            $where['price'] = $pre_data->price;
        }

        if ($data['account_money'] !== '' and array_key_exists('account_money', $data)) {
            $where['account_money'] = sprintf("%.2f", $data['account_money']);
        } else {
            $where['account_money'] = $pre_data->account_money;
        }

        $where['good_id'] = $data['good_id'];
        $where['inventory_id'] = $pre_data->inventory_id;

        if (!$data['good_id']) {
            echo 'good_id无效';
            exit;
        }

        $contractLinkData = $this->_getPlayContractLinkPriceTable()->get($where);

        unset($data['contract_link_id'], $data['contract_price_id']);//contract_link_id

        if (!$contractLinkData) {
            $data['inventory_id'] = $pre_data->inventory_id;
            $data['contract_id'] = $pre_data->contract_id;

            if ($pre_data) {
                $contractData = $this->_getPlayContractsTable()->get(array('id' => $pre_data->contract_id));
                if ($contractData->contracts_type == 1) {//包销限制数量
                    $data['total_num'] = 0;
                    $data['account_money'] = $pre_data->account_money;
                } elseif ($contractData->contracts_type != 3) {//代销
                    return false;
                }
            }

            if (!array_key_exists('money',$data)) {
                $data['money'] = $pre_data->money;
            }

            if (!array_key_exists('price',$data)) {
                $data['price'] = $pre_data->price;
            }

            if  (!array_key_exists('account_money',$data)) {
                $data['account_money'] = $pre_data->account_money;
            }

            $this->_getPlayContractLinkPriceTable()->insert($data);

            return $this->_getPlayContractLinkPriceTable()->getlastInsertValue();
        } else {
            $this->_getPlayContractLinkPriceTable()->update(['status' => 1], ['id' => $contractLinkData->id]);

            return $contractLinkData->id;
        }
    }

    /**
     * 不需要选取时间的套系
     * @return ViewModel
     */
    public function oldPlaceInfoAction()
    {
        $id = (int)$this->getQuery('id');
        $gid = (int)$this->getQuery('gid');

        $gameInfo = [];
        if ($id) {
            $gameInfo = $this->_getPlayGameInfoTable()->get(array('id' => $id, 'status = 1'));
        } elseif ($gid) {
            $gameInfo = $this->_getPlayGameInfoTable()->fetchLimit(0, 1, [], array('gid' => $gid, 'status = 1'),
                ['id' => 'asc'])->current();
        }


        $useOrganizerData = $this->_getPlayCodeUsedTable()->get(array(
            'good_id' => $gameInfo->gid,
            'good_info_id' => $id
        ));//使用商家
        $priceData = $this->_getPlayGamePriceTable()->fetchAll(array('gid' => $gid ?: $gameInfo->gid));//价格系列


        //商家 与 分店
        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid ?: $gameInfo->gid));
        $marketData = $this->_getPlayOrganizerTable()->get(array('id' => $goodData->organizer_id));
        $branchData = $this->_getPlayOrganizerTable()->fetchAll(array(
            'branch_id' => $goodData->organizer_id,
            'status > 0'
        ));

        $baoyou = new Baoyou();
        $baoyoulist = json_decode($baoyou->GetProductRateList()['Data'], true);

        $vm = new ViewModel(array(
            'baoyoulist' => $baoyoulist,
            'gameInfo' => $gameInfo,
            'goodData' => $goodData,
            'priceData' => $priceData,
            'useOrganizerData' => $useOrganizerData,
            'marketData' => $marketData,
            'branchData' => $branchData,
        ));

        return $vm;
    }


}
