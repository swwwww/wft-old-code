<?php

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Fun\M;
use library\Service\Admin\Setting\IndexBlock;
use library\Service\Kidsplay\Kidsplay;
use library\Service\Kidsplay\Shop;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;

class FirstController extends BasisController
{
    use JsonResponse;

    public function indexAction() {

        //link_type  关联的类型 1精选  2焦点图 3 公告 4优惠 5 右上角活动

        $city = $this->chooseCity();

        $data = array();

        //精选
        $choiceData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 1,'status > ?'=> 0), array('dateline' => 'DESC'));
        //焦点图
        $mapsData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 2,'status > ?'=> 0), array('dateline' => 'DESC'));
        //公告
        $topData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 3,'status > ?'=> 0), array('dateline' => 'DESC'));
        //优惠
        $saleData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 4,'status > ?'=> 0), array('dateline' => 'DESC'));
        //图标
        $cornerData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 6, 'status > ?'=> 0), array('dateline' => 'DESC'));
        //浮动
        $floatData = $this->_getPlayIndexBlockTable()->fetchAll(array('block_city' => $city, 'link_type' => 7,'status > ?'=> 0), array('dateline' => 'DESC'));


        $data['1'] = array(
            'name' => '精选',
            'number' => $choiceData->count(),
            'time' => $choiceData->current() ? date('Y-m-d H:i:s', $choiceData->current()->dateline) : '',
            'word' => '图文'
        );

        $data['2'] = array(
            'name' => '焦点图',
            'number' => $mapsData->count(),
            'time' => $mapsData->current() ? date('Y-m-d H:i:s', $mapsData->current()->dateline) : '',
            'word' => '图文'
        );

        $data['3'] = array(
            'name' => '公告',
            'number' => $topData->count(),
            'time' => $topData->current() ? date('Y-m-d H:i:s', $topData->current()->dateline) : '',
            'word' => '文字'
        );

        $data['4'] = array(
            'name' => '优惠',
            'number' => $saleData->count(),
            'time' => $saleData->current() ? date('Y-m-d H:i:s', $saleData->current()->dateline) : '',
            'word' => '图文'
        );

        $data['6'] = array(
            'name' => '图标',
            'number' => $cornerData->count(),
            'time' => $cornerData->current() ? date('Y-m-d H:i:s', $cornerData->current()->dateline) : '',
            'word' => '图标'
        );

        $data['7'] = array(
            'name' => '浮动框',
            'number' => $floatData->count(),
            'time' => $floatData->current() ? date('Y-m-d H:i:s', $floatData->current()->dateline) : '',
            'word' => '图文'
        );

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city,1),
        );

    }

    public function memberIndexAction() {

        //link_type  关联的类型 7 会员专区幻灯， 8 会员专区免费玩

        $city = $this->chooseCity();

        $data = array();

        // 幻灯（焦点图）
        $data_param_maps = array(
            'block_city' => $city,
            'link_type'  => 8,
            'status > ?' => 0
        );

        $data_order_maps = array(
            'dateline' => 'DESC'
        );

        $data_maps = IndexBlock::getIndexBlockDataList($data_param_maps, $data_order_maps);


        // 会员免费玩
        $data_param_free = array(
            'block_city' => $city,
            'link_type'  => 9,
            'status > ?' => 0
        );

        $data_order_free = array(
            'dateline' => 'DESC'
        );


        $data_free = IndexBlock::getIndexBlockDataList($data_param_free, $data_order_free);

        $data['8'] = array(
            'name'   => '幻灯（焦点图）',
            'number' => $data_maps->count(),
            'time'   => $data_maps->current() ? date('Y-m-d H:i:s', $data_maps->current()->dateline) : '',
            'word'   => '图文'
        );

        $data['9'] = array(
            'name'   => '会员免费玩',
            'number' => $data_free->count(),
            'time'   => $data_free->current() ? date('Y-m-d H:i:s', $data_free->current()->dateline) : '',
            'word'   => '图文'
        );

        return array(
            'data' => $data,
            'filtercity' => CityCache::getFilterCity($city,1),
        );

    }

    //列表
    public function listAction() {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $city = $this->chooseCity();
        $type = (int)$this->getQuery('type', 0);
        $time = time();

        $label = array('专题', '卡券', '资讯', '游玩地', '商品', 'html5', '话题', '圈子', '邀请', '积分', '账户', '秒杀', '优惠券', '游玩地类别','玩伴圈', '活动');

        $where = array(
            'block_city' => $city,
            'link_type' => $type,
            'status > ?' => 0
        );

        $start = ($page - 1) * $pageSum;

        $order = array('status' => 'desc', 'dateline' => 'desc');

        if ($type == 1) { //精选
            $search_type = $this->getQuery('search_type');
            $search_status = $this->getQuery('search_status');
            $search_id = (int)$this->getQuery('search_id');

            if (!$search_status) {
                if ($search_type) {
                    $where['type'] = $search_type;
                }
                if ($search_id) {
                    $where['link_id'] =  $search_id;
                }
                $blockData =  $this->_getPlayIndexBlockTable()->fetchLimit($start, $pageSum, array(), $where, $order);
                $count = $this->_getPlayIndexBlockTable()->fetchCount($where);

            } else {
                if ($search_status == 1) {
                    $where['is_top'] = 1;
                    if ($search_type) {
                        $where['type'] = $search_type;
                    }
                    if ($search_id) {
                        $where['link_id'] =  $search_id;
                    }
                    $blockData =  $this->_getPlayIndexBlockTable()->fetchLimit($start, $pageSum, array(), $where, $order);
                    $count = $this->_getPlayIndexBlockTable()->fetchCount($where);
                } elseif ($search_status == 2) {

                    $where['status'] = 2;
                    if ($search_type) {
                        $where['type'] = $search_type;
                    }
                    if ($search_id) {
                        $where['link_id'] =  $search_id;
                    }
                    $blockData =  $this->_getPlayIndexBlockTable()->fetchLimit($start, $pageSum, array(), $where, $order);
                    $count = $this->_getPlayIndexBlockTable()->fetchCount($where);

                } elseif ($search_status == 3 || $search_status == 4) {

                    if ($search_type == 4) {

                        $where['type'] = $search_type;

                        if ($search_id) {
                            $where['link_id'] =  $search_id;
                        }

                        if ($search_status == 3) {
                            $blockData =  $this->_getPlayIndexBlockTable()->fetchLimit($start, $pageSum, array(), $where, $order);
                            $count = $this->_getPlayIndexBlockTable()->fetchCount($where);
                        }

                    } elseif ($search_type == 5) {

                        $adapter = $this->_getAdapter();

                        $where_ser = "play_index_block.block_city = '" . $city . "' AND play_index_block.link_type = 1 AND play_index_block.status > 0 AND play_index_block.type= 5";

                        if ($search_id) {
                            $where_ser = $where_ser. " AND play_index_block.link_id = ". $search_id;
                        }

                        if ($search_status == 3) {
                            $where_ser = $where_ser. " AND ((play_organizer_game.start_time < ". time() . " AND play_organizer_game.end_time > ". time() . "  AND play_organizer_game.status = 1) OR play_organizer_game.is_together = 2)";
                        } else {
                            $timer = time();
                            $where_ser = $where_ser. " AND (play_organizer_game.is_together = 1 AND (play_organizer_game.status != 1 OR play_organizer_game.start_time > {$timer} OR play_organizer_game.end_time < {$timer}))";
                        }

                        $sql = "SELECT play_index_block.* FROM play_index_block LEFT JOIN play_organizer_game ON play_index_block.link_id = play_organizer_game.id WHERE $where_ser ORDER BY play_index_block.status DESC , play_index_block.dateline DESC";
                        $sql_list = $sql. " LIMIT {$start}, {$pageSum}";
                        $blockData =  $adapter->query($sql_list, array());
                        $count = $adapter->query($sql, array())->count();

                    } elseif ($search_type == 16) { //活动

                        $adapter = $this->_getAdapter();

                        $where_ser = "play_index_block.block_city = '" . $city . "' AND play_index_block.link_type = 1 AND play_index_block.status > 0 AND play_index_block.type= 16";

                        if ($search_id) {
                            $where_ser = $where_ser. " AND play_index_block.link_id = ". $search_id;
                        }

                        $timer = time();
                        if ($search_status == 3) {
                            $where_ser = $where_ser. " AND play_excercise_base.min_end_time > $timer";
                        } else {
                            $where_ser = $where_ser. " AND play_excercise_base.min_end_time < $timer";
                        }

                        $sql = "SELECT play_index_block.* FROM play_index_block LEFT JOIN play_excercise_base ON play_index_block.link_id = play_excercise_base.id WHERE $where_ser ORDER BY play_index_block.status DESC , play_index_block.dateline DESC";
                        $sql_list = $sql. " LIMIT {$start}, {$pageSum}";
                        $blockData =  $adapter->query($sql_list, array());
                        $count = $adapter->query($sql, array())->count();

                    }

                }  else {
                    echo 56;
                    exit;
                }

            }
        }  else {
            $blockData =  $this->_getPlayIndexBlockTable()->fetchLimit($start, $pageSum, array(), $where, $order);
            $count = $this->_getPlayIndexBlockTable()->fetchCount($where);
        }



        $url = '/wftadlogin/first/list';
        $paging = new Paginator($page, $count, $pageSum, $url);
        $data = array();

        // 关联的类型 1 专题 2 卡券 3 资讯 4 游玩地 5 商品 6圈子 7话题  8 html5
        foreach ($blockData as $val) {
            if ($val->type == 1) {//专题
                $vData = $this->_getPlayActivityTable()->getActivityList(0, 1, array(), array('play_activity.id' => $val->link_id))->current();
                $m_status = ($vData->status >= 0 && (($vData->s_time < $time && $vData->e_time > $time) || ($vData->s_time == 0 && $vData->e_time == 0))) ? 1 : 0;
                $click_num = $vData->activity_click;
                $post_num = 0;
                $share_num = $this->_getPlayShareTable()->fetchCount(array('type' => 'activity', 'share_id' => $val->link_id));
            } elseif ($val->type == 4) {//游玩地
                $vData = $this->_getPlayShopTable()->getAdminShopList(0, 1, array(), array('play_shop.shop_id' => $val->link_id))->current();
                $m_status = $vData->shop_status == 0 ? 1 : 0;
                $click_num = $vData->shop_click;
                $post_num = $vData->post_number;
                $share_num = $this->_getPlayShareTable()->fetchCount(array('type' => 'shop', 'share_id' => $val->link_id));
            } elseif ($val->type == 5) {//商品
                $vData = $this->_getPlayOrganizerGameTable()->getAdminEditor(0, 1, array(), array('play_organizer_game.id' => $val->link_id))->current();
                $m_status = (($vData->status == 1 && $vData->start_time < $time && $vData->end_time > $time && $vData->is_together == 1) ||  $vData->is_together == 2)? 1 : 0;
                $click_num = $vData->click_num;
                $post_num = $vData->post_number;

                $m_status = $m_status ? (($val->end_time == 0 || $val->end_time > time()) ? 1 : 0) : 0;
                $share_num = $this->_getPlayShareTable()->fetchCount(array('type' => 'game', 'share_id' => $val->link_id));
            } elseif (in_array($val->type,[6,9,10,11,12,13,14])) {//html5
                $m_status = 1;
                $click_num = 0;
                $post_num = 0;
                $share_num = 0;
            } elseif ($val->type == 7 || $val->type == 8) {//话题 圈子
                $m_status = 1;
                $click_num = 0;
                $post_num = 0;
                $share_num = 0;
            } elseif ($val->type == 16) {
                $m_status = 1;
                $click_num = 0;
                $post_num = 0;
                $share_num = 0;
            } elseif ($val->type == 17) {
                $m_status = 1;
                $click_num = 0;
                $post_num = 0;
                $share_num = 0;
            } elseif ($val->type == 18) {
                $m_status = 1;
                $click_num = 0;
                $post_num = 0;
                $share_num = 0;
            } else {
                return $this->_Goto('非法操作');

            }

            $data[] = array(
                'id' => $val->id,
                'block_title' => $val->block_title,
                'type' => $val->type,
                'block_order' => $val->block_order,
                'link_id' => $val->link_id,
                'mid' => 1,
                'city' => $val->block_city,
                'flag' => isset($m_status)? $m_status : 0,
                'status' => $val->status,
                'tip' => $val->tip,
                'link_type' =>$val->link_type,
                'click_num' => $click_num,
                'post_num' => $post_num,
                'share_num' => $share_num,
                'editor' => $val->editor,
                'is_top' => $val->is_top,
            );
        }

        $data = array(
            'data' => $data,
            'pageData' => $paging->getHtml(),
            'label' => $label,
            'count' => $count,
         );

        if ($type == 6) {
            $category = $this->_getPlayLabelTable()->fetchAll(['label_type' => 2,'status > ?' => 1, 'city' => $city]);
            $data['category'] = $category;
        }

        $vm = new ViewModel($data);

        if ($type == 1) { //精选
            $vm->setTemplate('admin/first/list_choice.phtml');
        } elseif($type == 2) { //焦点图
            $vm->setTemplate('admin/first/list_focus_picture.phtml');
        } elseif ($type == 3) {//公告
            $vm->setTemplate('admin/first/list_top_talk.phtml');
        } elseif ($type == 4) {//优惠
            $vm->setTemplate('admin/first/list_sale.phtml');
        } elseif ($type == 6) {//图标
            $vm->setTemplate('admin/first/list_module_pic.phtml');
        } elseif ($type == 7) {//浮动框
            $vm->setTemplate('admin/first/list_float_img.phtml');
        } elseif ($type == 8) {//会员专区幻灯
            $vm->setTemplate('admin/first/list_focus_picture_member.phtml');
        } else {
            return $this->_Goto('非法操作');
        }

        return $vm;

    }

    //不同类型的添加web
    public function addAction() {

        $type = (int)$this->getQuery('type', 0);
        $link_type = (int)$this->getQuery('link_type', 0);
        $like = $this->getQuery('k', '');


        if (!in_array($type, array(1, 2, 3, 4, 6, 7, 8))) {
            return $this->_Goto('非法操作');
        }

        if (!in_array($link_type, array(0, 1, 4, 5, 7, 8,16))) {
            return $this->_Goto('非法操作');
        }

        $data = array();
        //1 专题 2 卡券 3 资讯 4 游玩地 5 商品 7 话题  8圈子
        if ($like) {
            if ($link_type === 1) { // 专题
                $id = (int)$like;
                $where = array(
                    'play_activity.ac_city' => $_COOKIE['city'],
                    'play_activity.status > ?' => -1,
                    "(play_activity.ac_name like  '%" . $like . "%' || play_activity.id = $id)",
                );
                $res = $this->_getPlayActivityTable()->fetchLimit(0, 20, array(), $where);
                foreach ($res as $v) {
                    $data[] = array(
                        'id' => $v->id,
                        'username' => $v->uid,
                        'create_time' => $v->dateline,
                        'name' => $v->ac_name,
                    );
                }
            }

            if ($link_type === 4) { // 游玩地
                $id = (int)$like;
                $where = array(
                    'play_shop.shop_city' => $_COOKIE['city'],
                    'play_shop.shop_status >= ?' => 0,
                    "(play_shop.shop_name like  '%" . $like . "%' || play_shop.shop_id = $id)",
                );
                $res = $this->_getPlayShopTable()->fetchLimit(0, 20, array(), $where);
                foreach ($res as $v) {
                    $data[] = array(
                        'id' => $v->shop_id,
                        'username' => $v->editor_id,
                        'create_time' => $v->dateline,
                        'name' => $v->shop_name,
                    );
                }
            }

            if ($link_type === 5) { //商品
                $id = (int)$like;
                $where = array(
                    'play_organizer_game.city' => $_COOKIE['city'],
                    'play_organizer_game.status > ?' => 0,
                    "(play_organizer_game.title like  '%" . $like . "%' || play_organizer_game.id = $id)",
                );
                $res = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 20, array(), $where, array());
                foreach ($res as $v) {
                    $data[] = array(
                        'id' => $v->id,
                        'username' => $v->editor_id,
                        'create_time' => $v->dateline,
                        'name' => $v->title,
                    );
                }
            }

            if ($link_type === 7) {//话题

                $msgData = array();
                if ($this->checkMid($like)) {
                    $msgData = $this->_getMdbSocialCircleMsg()->find(array("_id" => new \MongoId($like), 'status' => array('$gt' => 0)))->limit(20);
                }

                foreach ($msgData as $msg) {
                    $data[] = array(
                        'id' => $msg['_id'],
                        'username' => $msg['uid'],
                        'create_time' => $msg['dateline'],
                        'name' => $msg['title'],
                    );
                }
            }

            if ($link_type === 8) {
                $msgData = $this->_getMdbSocialCircle()->find(array("title" => new \MongoRegex("/$like/"), 'status' => array('$gt' => 0)))->limit(20);
                foreach ($msgData as $msg) {
                    $data[] = array(
                        'id' => $msg['_id'],
                        'username' => $msg['uid'],
                        'create_time' => $msg['dateline'],
                        'name' => $msg['title'],
                    );
                }
            }

            if ($link_type === 16) {
                $id = (int)$like;

                $where = array(
                    'play_excercise_base.city' => $_COOKIE['city'],
                    'play_excercise_base.release_status > ?' => 0,
                    "(play_excercise_base.name like  '%" . $like . "%' || play_excercise_base.id = $id)",
                );

                $msgData = $this->_getPlayExcerciseBaseTable()->fetchLimit(0,20,array(),$where);
                foreach ($msgData as $v) {
                    $data[] = array(
                        'id' => $v->id,
                        'username' => 0,
                        'create_time' => $v->add_dateline,
                        'name' => $v->name,
                    );
                }
            }

        }

        $haveData = $this->_getPlayIndexBlockTable()->fetchLimit(0,5000, array('link_id'), array('type' => $link_type, 'link_type' => $type, 'status > ?' => 0, 'block_city' => $_COOKIE['city']))->toArray();

        $data = array(
            'link_type' => $link_type,
            'data' => $data,
            'have' => $haveData,
        );

        $vm = new ViewModel($data);

        if ($type == 1) { //精选
            $vm->setTemplate('admin/first/add_choice.phtml');
        } elseif($type == 2) { //焦点图
            $vm->setTemplate('admin/first/add_focus_picture.phtml');
        } elseif ($type == 3) {//公告
            $vm->setTemplate('admin/first/add_top_talk.phtml');
        } elseif ($type == 4) {//优惠
            $vm->setTemplate('admin/first/add_sale.phtml');
        } elseif ($type == 6) {//图标
            $vm->setTemplate('admin/first/add_module_pic.phtml');
        } elseif ($type == 7) {//浮动框
            $vm->setTemplate('admin/first/add_float_img.phtml');
        } elseif ($type == 8) {//会员专区幻灯
            $vm->setTemplate('admin/first/add_focus_picture_member.phtml');
        } else {
            return $this->_Goto('非法操作');
        }

        return $vm;

    }

    //关联操作
    //link_type 关联类型 1精选  2焦点图 3 今日头条(公告) 4优惠 5 右上角活动 6图标 7浮动框
    //type 类别 1 专题 2 卡券 3 资讯 4 游玩地 5 商品 6 html5页面 7话题  8 圈子 9邀请 10积分 11账户 12秒杀 13优惠券 14游玩地类别 16活动
    public function saveAction() {

        $type = (int)$this->getQuery('type', 0); //首页的模块
        if (!in_array($type, array(1, 2, 3, 4, 6, 7, 8, 9))) {
            return $this->_Goto('非法操作');
        }

        $link_type = (int)$this->getQuery('link_type');//引用的类别
        if (!in_array($link_type, array(1, 4, 5, 7, 8,16))) {
            return $this->_Goto('非法操作');
        }

        $link_id = $this->getPost('link_id', 0);//引用对象id
        if (!$link_id) {
            return $this->_Goto('非法操作');
        }

        $block_city = $_COOKIE['city'];

        $flag = $this->_getPlayIndexBlockTable()->get(array('link_id' => $link_id, 'status > 0', 'link_type' => $type, 'type' => $link_type, 'block_city' => $block_city));

        if ($flag) {
            return $this->_Goto('已经关联了');
        }

        $count = $this->_getPlayIndexBlockTable()->fetchCount(array('link_type' => $type, 'status > 0', 'block_city' => $block_city));

        // todo 数量限制
        //1精选  2焦点图 5 3 公告 10 4优惠 30  6图标 5 7悬浮框 1
        if (($type === 2 && $count >= 5) || ($type === 3 && $count >= 10) || ($type === 4 && $count >= 30) || ($type === 6 && $count >= 5) || ($type === 7 && $count >= 1) || ($type === 8 && $count >= 4)) {
            return $this->_Goto('超出了最大数量');
        }

        $cover = $this->getPost('cover');
        $title = $this->getPost('title');
        $tip = $this->getPost('tip', '');


        if ($type == 1) { //精选
            if (!$title) {
                return $this->_Goto('标题未填写');
            }

            if (!$tip) {
                return $this->_Goto('描述未填写');
            }

            if (!$cover) {
                return $this->_Goto('封面图');
            }
            $this->scaleCover($cover);
        }

        if ($type == 2) { //焦点图
            if (!$title) {
                return $this->_Goto('标题未填写');
            }

            if (!$cover) {
                return $this->_Goto('封面图');
            }

            $this->scaleCover($cover);
        }

        if ($type == 3) { //公告
            if (!$title) {
                return $this->_Goto('标题未填写');
            }
        }

        if ($type == 4) { //优惠
            if (!$title) {
                return $this->_Goto('标题未填写');
            }

            if (!$cover) {
                return $this->_Goto('图片');
            }
            $this->scaleCover($cover, 360, 360);
        }

        if ($type == 6) { //图标
            if (mb_strlen($title, 'UTF8') < 1 || mb_strlen($title, 'UTF8') > 4) {
                return $this->_Goto('图标说明文字不能大于4个');
            }

            if (!$cover) {
                return $this->_Goto('封面图');
            }
            $this->scaleCover($cover, 84, 84);
        }

        $mult_covers = '';
        if ($type == 7) {//悬浮框
            if (!$title) {
                return $this->_Goto('悬浮说明');
            }

            $cover = array_filter($cover);

            if (!count($cover)) {
                return $this->_Goto('图片');
            }

//            foreach($cover as $c){
//                $this->scaleCover($c, 128, 128);
//            }
            $mult_covers = json_encode($cover);
            $cover = '';
        }

        $end_time = 0;
        if ($link_type == 5) {
            $end_l = $this->getPost('end_timer', '');
            $end_r = $this->getPost('end_timerl', '');

            if ($end_l && $end_r) {
                $end_time = strtotime($end_l . $end_r); //下架时间
            }
        }

        $status = M::getPlayIndexBlockTable()->insert(array(
            'link_id' => $link_id,
            'type' => $link_type,
            'block_city' => $block_city,
            'dateline' => time(),
            'link_type' => $type,
            'status' => 1,
            'link_img' => $cover,
            'block_title' => $title,
            'tip' => $tip,
            'mult_covers' => $mult_covers,
            'editor' => $_COOKIE['user'],
            'editor_id' => $_COOKIE['id'],
            'end_time' => $end_time,
        ));
        return $this->_Goto($status ? '成功' : '失败','/wftadlogin/first/list?type='.$type);
    }


    private function scaleCover($cover,$w=720,$h=360){
        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);
        $cover_status = $cover_class->scaleResizeImage($w, $h);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
        }
    }

    //保存 web——url
    public function urlsaveAction(){
        $url = $this->getPost('url', 0);//具体内容id
        $cover = $this->getPost('cover', '');
        $title = trim($this->getPost('title', ''));
        $tip = $this->getPost('tip','');
        $link_type = (int)$this->getQuery('link_type', 0); //首页的模块
        $block_city = $_COOKIE['city'];
        $type = 6;

        $mult_covers = '';

        if (!in_array($link_type, array(1, 2, 3, 4, 6, 7, 8, 9))) {
            return $this->_Goto('非法操作');
        }

        if ($link_type == 7) {//悬浮框
            $cover = array_filter($cover);

            if (!count($cover)) {
                return $this->_Goto('图片');
            }

//            foreach($cover as $c){
//                $this->scaleCover($c, 200, 200);
//            }
            $mult_covers = json_encode($cover);

        } elseif ($link_type == 6) {//图标
            if (!$cover) {
                return $this->_Goto('图片');
            }
            $this->scaleCover($cover,84, 84);
        } else {
            if (!$cover && $link_type != 3) { //非公告
                return $this->_Goto('图片');
            }
            $this->scaleCover($cover);
        }

        $data = null;
        $link_id = 0;

        if ($link_type == 2) { //焦点图
            //判断个数是否超出5个
            $count = $this->_getPlayIndexBlockTable()->fetchCount(array('block_city' => $block_city, 'status > 0', 'link_type' => 2));
            if ($count >= 5) {
                return $this->_Goto('公告数量已经超出5个啦');
            }

            if (!$url) {
                return $this->_Goto('URL地址不能为空');
            }
        }

        if ($link_type == 3) { //公告
            //判断个数是否超出5个
            $count = $this->_getPlayIndexBlockTable()->fetchCount(array('block_city' => $block_city, 'status > 0', 'link_type' => 3));
            if ($count >= 10) {
                return $this->_Goto('公告数量已经超出10个啦');
            }
            if (!$title) {
                return $this->_Goto('公告文字不能为空');
            }
            if (!$url) {
                return $this->_Goto('URL地址不能为空');
            }
        }

        if ($link_type == 7) { //悬浮框
            //判断个数是否超出1个
            $count = $this->_getPlayIndexBlockTable()->fetchCount(array('block_city' => $block_city, 'status > 0', 'link_type' => 7));
            if ($count >= 1) {
                return $this->_Goto('悬浮框数量已经超出1个啦');
            }
            if (!$title) {
                return $this->_Goto('浮动说明不能为空');
            }
            if (!$url) {
                return $this->_Goto('URL地址不能为空');
            }
            //todo 图片判断
        }

        if ($link_type == 1) {//精选
            if (!$title) {
                return $this->_Goto('标题文字不能为空');
            }
            if (!$tip) {
                return $this->_Goto('说明文字不能为空');
            }
            if (!$url) {
                return $this->_Goto('URL地址不能为空');
            }
        }

        if ($link_type == 4) {//优惠
            //无
        }

        if ($link_type == 6) {//图标

            $count = $this->_getPlayIndexBlockTable()->fetchCount(array('block_city' => $block_city, 'status > 0', 'link_type' => 6));
            if ($count >= 7) {
                return $this->_Goto('图标数量已经超出7个啦');
            }

            $category = (int)$this->getPost('category', 0);
            $label_type = (int)$this->getPost('label_type', 0);

            if (!in_array($category, array(6, 9, 10, 11, 12, 13, 14, 17, 18))) {
                return $this->_Goto('非法操作');
            }

            if (in_array($category, array(9, 10, 11, 12, 13, 17, 18))) {
                $flag = $this->_getPlayIndexBlockTable()->get(array('type' => $category, 'block_city' => $block_city, 'status > 0', 'link_type' => 6));

                if ($flag) {
                    return $this->_Goto('该模块已存在');
                }
            }

            $type = $category;

            if ($category == 14) {
                $link_id = $label_type;
            }

            if (mb_strlen($title, 'UTF8') < 1 || mb_strlen($title, 'UTF8') > 4) {
                return $this->_Goto('图标说明文字不能大于4个');
            }

            if (!$url && $category == 6) {
                return $this->_Goto('URL地址不能为空');
            }
            if (!$cover) {
                return $this->_Goto('图片');
            }
        }

        if (!$data) {
            $data = array(
                'link_id' => $link_id,
                'type' => $type,
                'block_city' => $block_city,
                'dateline' => time(),
                'link_type' => $link_type,
                'link_img' => $cover,
                'block_title' => $title,
                'url' => $url,
                'tip' => $tip,
                'status' => 1,
                'mult_covers' => $mult_covers,
                'editor' => $_COOKIE['user'],
                'editor_id' => $_COOKIE['id'],
            );
        }


        $status = $this->_getPlayIndexBlockTable()->insert($data);

        return $this->_Goto($status ? '成功' : '失败');
    }

    public function excerciseFreeListAction () {
        $param['page']       = (int)$this->getQuery('p', 1);
        $param['page_num']   = (int)$this->getQuery('page_num', 10);
        $param['name']       = $this->getQuery('name', '');
//        $param['start_time'] = $this->getQuery('start_time', 0);
//        $param['end_time']   = $this->getQuery('end_time', 0);
        $param['bid']        = (int)$this->getQuery('bid', 0);

        $param['city']       = $this->getAdminCity();

        $param['start']      = ($param['page'] - 1) * $param['page_num'];
        $param['start']      = ($param['start'] < 0) ? 0 : $param['start'];

        $param['free_coupon']= 1;

        $data_count     = Kidsplay::getKidsplayList($param, $param['start'], $param['page_num'], 1);

        // 分页
        $data_url       = '/wftadlogin/first/excercisefreelist';
        $data_paginator = new Paginator($param['page'], $data_count, $param['page_num'], $data_url);

        $data_kidsplay_list = Kidsplay::getKidsplayList($param, $param['start'], $param['page_num']);


        $data_base_id     = array();

        if ($data_kidsplay_list) {
            foreach ($data_kidsplay_list as $key => $val) {
                $data_base_id[] = $val['id'];
            }
        } else {
            $data_base_id= 0;
        }

        $data_param_shop = array(
            'bid'      => $data_base_id,
            'is_close' => 0
        );
        $data_temp_shop = Shop::getShopListForExcercise($data_param_shop, 0, 100);
        $data_shop      = array();
        if ($data_temp_shop) {
            foreach ($data_temp_shop as $s) {
                $data_shop[$s['bid']] .= $s['shop_name'] . ',<br>';
            }
        }

        $data_param_index_block = array(
            'link_id'   => $data_base_id,
            'type'      => 16,
            'link_type' => 9,
        );
        $data_temp_index_block = IndexBlock::getIndexBlockDataList($data_param_index_block);
        $data_index_block      = array();
        if ($data_temp_index_block) {
            foreach ($data_temp_index_block as $key => $val) {
                $data_index_block[$val['link_id']] = $val;
            }
        }

        $data_temp_price_count = Kidsplay::getFreeJoinCountForExcercise($data_base_id);
        $data_price_count      = array();
        if ($data_temp_price_count) {
            foreach ($data_temp_price_count as $key => $val) {
                $data_price_count[$val['bid']] = $val['free_join_count'];
            }
        }

        $vm = new viewModel(
            array(
                'data_kidsplay_list' => $data_kidsplay_list,
                'data_index_block'   => $data_index_block,
                'data_paginator'     => $data_paginator->getHtml(),
                'data_shop'          => $data_shop,
                'data_price_count'   => $data_price_count,
            )
        );

        $vm->setTemplate('admin/first/excercise_free_list.phtml');

        return $vm;
    }

    public function eventFreeListAction () {
        $param['page']       = (int)$this->getQuery('p', 1);
        $param['page_num']   = (int)$this->getQuery('page_num', 10);
        $param['name']       = $this->getQuery('name', '');
        $param['start_time'] = $this->getQuery('start_time', 0);
        $param['end_time']   = $this->getQuery('end_time', 0);
        $param['bid']        = (int)$this->getQuery('bid', 0);
        $param['eid']        = (int)$this->getQuery('eid', 0);

        $param['city']       = $this->getAdminCity();

        $param['start']      = ($param['page'] - 1) * $param['page_num'];
        $param['start']      = ($param['start'] < 0) ? 0 : $param['start'];

        $param['free_coupon']= 1;

        $data_count     = Kidsplay::getEventList($param, $param['start'], $param['page_num'], 1);

        // 分页
        $data_url       = '/wftadlogin/first/eventfreelist';
        $data_paginator = new Paginator($param['page'], $data_count, $param['page_num'], $data_url);

        $data_event_list = Kidsplay::getEventList($param, $param['start'], $param['page_num']);

        $data_base_id    = array();

        if ($data_event_list) {
            foreach ($data_event_list as $key => $val) {
                $data_base_id[] = $val['bid'];
            }
        } else {
            $data_base_id= 0;
        }

        $data_param_shop = array(
            'bid'      => $data_base_id,
            'is_close' => 0
        );
        $data_temp_shop = Shop::getShopListForExcercise($data_param_shop, 1, 100);
        $data_shop      = array();
        if ($data_temp_shop) {
            foreach ($data_temp_shop as $s) {
                $data_shop[$s['bid']] .= $s['shop_name'] . ',<br>';
            }
        }

        $vm = new viewModel(
            array(
                'data_event_list' => $data_event_list,
                'data_paginator'  => $data_paginator->getHtml(),
                'data_shop'       => $data_shop,
            )
        );

        $vm->setTemplate('admin/first/event_free_list.phtml');

        return $vm;
    }

    public function excercisefreelistinmemberindexAction () {
        $param['id']     = (int)$this->getQuery('id', 0);
        $param['status'] = (int)$this->getQuery('status', 1);

        $data_kidsplay_base = Kidsplay::getKidsplayBaseById ($param['id']);

        $data_param_status = array(
            'status'     => $param['status'],
            'block_title'=> $data_kidsplay_base['name'],
            'link_img'   => $data_kidsplay_base['cover'],
            'dateline'   => time(),
        );

        $data_param_where= array(
            'link_id'    => $param['id'],
            'type'       => 16,
            'block_city' => $this->getAdminCity(),
            'link_type'  => 9,
        );
        $data_return     = IndexBlock::setIndexBlock($data_param_status, $data_param_where);

        if ($data_return) {
            return $this->_Goto("操作成功");
        } else {
            return $this->_Goto("操作失败");
        }
    }

    public function cancelFreePriceAction () {
        $param['eid'] = (int)$this->getQuery('id', 0);
        $param['city']= $this->getAdminCity();
        $data_return  = Kidsplay::cancelFreePrice($param);

        if ($data_return) {
            return $this->_Goto("操作成功");
        } else {
            return $this->_Goto("操作失败");
        }
    }

    //强推
    public function pushUpAction() {
        $id = (int)$this->getQuery('id', 0);
        $type = (int)$this->getQuery('type', 1);
        $city = $_COOKIE['city'];

        //类型 1精选    4优惠
        if (!in_array($type, array(1, 4))) {
            return $this->_Goto('非法操作');
        }
        $count = $this->_getPlayIndexBlockTable()->fetchCount(array('is_top' => 1, 'link_type' => $type, 'block_city' => $city, 'status > ?' => 0));
        if ($count >= 3) {
            return $this->_Goto('失败,强推数量已经过了3个');
        }

        $status = $this->_getPlayIndexBlockTable()->update(array('is_top' => 1), array('id' => $id, 'link_type' => $type, 'block_city' => $city));
        return $this->_Goto($status ? '成功' : '失败');
    }

    //取消强推
    public function pushDownAction() {
        $id = (int)$this->getQuery('id', 0);
        $city = $_COOKIE['city'];

        $status = $this->_getPlayIndexBlockTable()->update(array('is_top' => 0), array('id' => $id, 'block_city' => $city));
        return $this->_Goto($status ? '成功' : '失败');
    }

    //置顶
    public function upAction() {
        $id = (int)$this->getQuery('id', 0);
        $city = $_COOKIE['city'];

        $data = $this->_getPlayIndexBlockTable()->get(array('id' => $id, 'block_city' => $city));

        if (!$data) {
            return $this->_Goto('非法操作');
        }

        $count = $this->_getPlayIndexBlockTable()->fetchCount(array('status' => 2, 'link_type' => $data->link_type, 'block_city' => $city));

        //1精选  2焦点图 3 今日头条(公告) 4优惠 5 右上角活动 6图标 7浮动框
        if (($data->link_type == 1 || $data->link_type == 4) && $count >= 5) {
            $note = ($data->link_type == 1) ? '精选' : '优惠';
            return $this->_Goto($note. '优惠置顶数量不能超过5个');
        }


        $status = $this->_getPlayIndexBlockTable()->update(array('dateline' => time(), 'status' => 2), array('id' => $id));
        return $this->_Goto($status ? '成功' : '失败');
    }

    //取消置顶
    public function downAction() {
        $id = (int)$this->getQuery('id', 0);
        $city = $_COOKIE['city'];
        $status = $this->_getPlayIndexBlockTable()->update(array('dateline' => time(), 'status' => 1), array('id' => $id, 'block_city' => $city));
        return $this->_Goto($status ? '成功' : '失败');
    }


    //删除
    public function deleteAction() {
        $id = (int)$this->getQuery('id', 0);
        //$city = $_COOKIE['city'];
        $status = $this->_getPlayIndexBlockTable()->update(array('status' => 0), array('id' => $id));
        return $this->_Goto($status ? '成功' : '失败');
    }

    public function searchAction() {
        $type = (int)$this->getQuery('type');
        $id = $this->getQuery('id');

        $data = null;
        if ($type == 1) {//专题
            $activityData = $this->_getPlayActivityTable()->get(array('id' => $id));
            if ($activityData) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => $activityData->ac_name, 'tip' => '', 'cover' => $activityData->ac_cover));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));
        }

        if ($type == 4) { //游玩地
            $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $id));
            if ($shopData) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => $shopData->shop_name, 'tip' => $shopData->editor_word, 'cover' => $shopData->cover));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));

        }

        if ($type == 5) { //商品
            $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
            if ($goodData) {
                $end_time = '<th width="160">取消推送时间</th>
            <th colspan="4">
                <input type="date" value="' . date('Y-m-d', $goodData->down_time) . '" name="end_timer">
                <input type="time" value="' . date('H:i', $goodData->down_time) . '" name="end_timerl">　
            </th>';
                return $this->jsonResponsePage(array('status' => 1, 'title' => $goodData->title, 'tip' => $goodData->editor_talk, 'cover' => $goodData->cover, 'thumb' => $goodData->thumb, 'end_time' => $end_time));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));
        }

        if ($type == 7) { //话题
            $flag = $this->checkMid($id);
            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));
            }
            $msgData = $this->_getMdbSocialCircleMsg()->findOne(array('_id' => new \MongoId($id)));
            if ($msgData) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => $msgData['title'], 'tip' => '', 'cover' => ''));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));

        }

        if ($type == 8) { //圈子
            $flag = $this->checkMid($id);
            if (!$flag) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));
            }
            $circleData = $this->_getMdbSocialCircle()->findOne(array('_id' => new \MongoId($id)));
            if ($circleData) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => $circleData['title'], 'tip' => $circleData['introduce'], 'cover' => $circleData['img']));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));

        }

        if ($type == 16) {
            $exerciseData = $this->_getPlayExcerciseBaseTable()->get(array('id' => $id));

            if ($exerciseData) {
                return $this->jsonResponsePage(array('status' => 1, 'title' => $exerciseData->name, 'tip' => $exerciseData->introduction, 'cover' => $exerciseData->cover));
            }
            return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));
        }

        return $this->jsonResponsePage(array('status' => 1, 'title' => '', 'tip' => '', 'cover' => ''));

    }

    public function viewAction() {

        $id = (int)$this->getQuery('id', 0);
        $data = $this->_getPlayIndexBlockTable()->get(array('id' => $id));

        if (!$data) {
            return $this->_Goto('非法操作');
        }

        $vm = new ViewModel(array(
            'data' => $data,
        ));

        return $vm;

    }

    public function saveFirstAction() {
        $id = (int)$this->getPost('id');
        $title = trim($this->getPost('title'));
        $tip = trim($this->getPost('tip'));
        $cover = trim($this->getPost('cover'));

        if (!$title) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '标题'));
        }

        $firstData = $this->_getPlayIndexBlockTable()->get(array('id' => $id));

        if (!$firstData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $data = array(
            'block_title' => $title,
        );

        if ($firstData->link_type == 1) {//精选
            if (!$tip) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '说明'));
            }
            if (!$cover) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '图片'));
            }

            $data['tip'] = $tip;
            $this->scaleCover($cover);
            $data['link_img'] = $cover;
        }

        if ($firstData->link_type == 4) {//优惠
            if (!$cover) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '图片'));
            }
            $this->scaleCover($cover, 360, 360);
            $data['link_img'] = $cover;
        }

        if (($firstData->link_type == 6) && (mb_strlen($title, 'UTF8') < 1 || mb_strlen($title, 'UTF8') > 4)) {//图标
            return $this->jsonResponsePage(array('status' => 0, 'message' => '图标说明文字不能大于4个'));
        }


        $end_time = 0;
        if ($firstData->type == 5) {
            $end_l = $this->getPost('end_timer', '');
            $end_r = $this->getPost('end_timerl', '');

            if ($end_l && $end_r) {
                $end_time = strtotime($end_l . $end_r); //下架时间
            }
        }

        $data['end_time'] = $end_time;


        $status = $this->_getPlayIndexBlockTable()->update($data, array('id' => $id));

        return $this->jsonResponsePage(array('status' => $status, 'message' => $status ? '成功' : '失败'));


    }

    //刷新上浮
    public function floatAction()
    {
        $id = (int)$this->getQuery('id');
        $this->_getPlayIndexBlockTable()->update(array('dateline' => time()), array('id' => $id));
        return $this->_Goto('成功');

    }


    //检查活动或商品 是否可以售卖
    public function checkStatusAction(){

        $id=$this->getQuery('id');
        $type=$this->getQuery('type');

        $db=$this->_getAdapter();
        if($type==16){
           $res= $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,circle,e.open_time,e.over_time,e.join_number,e.perfect_number,
	e.id as eid
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE
 b.id=?
AND e.customize = 0
AND b.release_status=1
AND e.sell_status>=1
AND e.sell_status!=3
AND e.join_number<e.perfect_number
AND e.open_time<? 
AND e.over_time>?
",array($id,time(),time()))->current();
            if(!$res){
                return $this->jsonResponsePage(array('status'=>0,'message'=>'不可售卖活动,无法推送,请检查活动或场次设置'));
            }
        }elseif($type==5){
            $res= $db->query("SELECT play_game_info.*,play_organizer_game.coupon_vir FROM play_game_info LEFT JOIN  play_organizer_game ON  play_organizer_game.id=play_game_info.gid
  WHERE play_game_info.gid=?  and play_game_info.status=1 and play_game_info.total_num > play_game_info.buy  AND play_game_info.up_time<?  and play_game_info.down_time>?
",array($id,time(),time()))->current();
            if(!$res){
                return $this->jsonResponsePage(array('status'=>0,'message'=>'不可售卖商品,无法推送,请检查商品或套系设置'));
            }
        }
          return $this->jsonResponsePage(array('status'=>1,'message'=>'ok'));
    }
}