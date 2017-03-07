<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;

class FirstPageController extends BasisController
{
    use JsonResponse;

    public function indexAction() {
        $page = (int)$this->getQuery('p', 1);
        $city = $_COOKIE['city'];
        $map = (int)$this->getQuery('map', 0);
        $type = $this->getQuery('type', '');
        $pageSum = 10;
        $where = array(
            'block_city = ?' => $city,
        );
        if ($map) {
            $where['link_type'] = $map;
        }
        if ($type) {
            $where['type'] = $type ;
        }
       // exit;
        $start = ($page - 1) * $pageSum;
        $order = array('block_order' => 'desc', 'play_index_block.dateline' => 'desc');
        $blockData =  $this->_getPlayIndexBlockTable()->getAdminIndexBlockList($start, $pageSum, array(), $where, $order);
        $count = $this->_getPlayIndexBlockTable()->getAdminIndexBlockCount($where);
        $url = '/wftadlogin/firstpage/index';
        $paginator = new Paginator($page, $count, $pageSum, $url);
        $data = array();
        $mid = '';
        $time = time();
        foreach ($blockData as $val) {
            if ($val->type == 1) {//专题
                $vData = $this->_getPlayActivityTable()->getActivityList(0, 1, array(), array('play_activity.id' => $val->link_id))->current();
                $title = $vData->ac_name;
                $m_status = ($vData->status >= 0 && (($vData->s_time < $time && $vData->e_time > $time) || ($vData->s_time == 0 && $vData->e_time == 0))) ? 1 : 0;
                $click_num = $vData->activity_click;
            } elseif ($val->type == 2) {//卡券
                $vData = $this->_getPlayCouponsTable()->getCouponsList(0, 1, array(), array('play_coupons.coupon_id' => $val->link_id))->current();
                $title = $vData->coupon_name;
                $mid = $vData->coupon_marketid;
                $m_status = ($vData->coupon_join == 0 || ($vData->coupon_status == 1 && $vData->coupon_uptime <= $time && $vData->coupon_starttime <= $time && $vData->coupon_endtime >= $time && $vData->coupon_total > $vData->coupon_buy)) ? 1 : 0;
                $click_num = 0;
            } elseif ($val->type == 3) {//资讯
                $vData = $this->_getPlayNewsTable()->getAdminNewsList(0, 1, array(), array('play_news.id' => $val->link_id))->current();
                $title = $vData->title;
                $m_status = $vData->status == 1 ? 1 : 0;
                $click_num = 0;
            } elseif ($val->type == 4) {//游玩地
                $vData = $this->_getPlayShopTable()->getAdminShopList(0, 1, array(), array('play_shop.shop_id' => $val->link_id))->current();
                $title = $vData->shop_name;
                $m_status = $vData->shop_status == 0 ? 1 : 0;
                $click_num = $vData->shop_click;
            } elseif ($val->type == 5) {//活动
                $vData = $this->_getPlayOrganizerGameTable()->getAdminEditor(0, 1, array(), array('play_organizer_game.id' => $val->link_id))->current();
                $title = $vData->title;
                $m_status = ($vData->status == 1 && $vData->start_time < $time && $vData->end_time > $time)? 1 : 0;
                $click_num = $vData->click_num;
            } elseif ($val->type == 7) {// 评论
                $mongo = $this->_getMongoDB();
                $vData = $mongo->social_circle_msg->findOne(array('_id' => new \MongoId($val->tip)));
                $title = $vData['title'];
                $m_status = 1;
                $click_num = '';
            }

            $data[] = array(
                'id' => $val->id,
                'title' => $title,
                'name' =>  ($val->type == 7) ?  '' : $vData->admin_name,
                'post_num' => ($val->type == 7) ?  '' : $vData->post_number,
                'type' => $val->type,
                'block_order' => $val->block_order,
                'link_id' => $val->link_id,
                'mid' => $mid,
                'city' => $val->block_city,
                'flag' => $m_status,
                'tip' => $val->tip,
                'link_type' =>$val->link_type,
                'click_num' => $click_num,
            );
        }

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'city' => $this->_getConfig()['city'][$city],
        );
    }

    //排序 及 删除
    public function changeAction() {

        $type = $this->getQuery('type', 1);
        if ($type == 1) {//删除
            $bid = (int)$this->getQuery('bid', 0);
            $this->_getPlayIndexBlockTable()->delete(array('id' => $bid));
            return $this->_Goto('成功');
        } elseif ($type == 2) {
            $oid = (int)$this->getQuery('oid', 1);
            $bid = (int)$this->getQuery('bid', 0);
            $v = (int)$this->getQuery('v', 0);

            if ($v == 1) {
                if ($oid != 399) {
                    return $this->_Goto('非法操作');
                }
                $num = $this->_getPlayIndexBlockTable()->fetchCount(array('block_order' => 399));
                if ($num >= 3) {
                    return $this->_Goto('失败,置顶数量多了');
                }

                $stu = $this->_getPlayIndexBlockTable()->update(array('block_order' => $oid), array('id' => $bid));
                if ($stu) {
                    return $this->_Goto('成功');
                } else {
                    return $this->_Goto('失败');
                }
            } elseif ($v == 2) {
                if ($oid >= 399) {
                    return $this->_Goto('非法操作');
                }
                $stu = $this->_getPlayIndexBlockTable()->update(array('block_order' => $oid), array('id' => $bid));
                if ($stu) {
                    return $this->_Goto('成功');
                } else {
                    return $this->_Goto('失败');
                }

            } else {
                return $this->_Goto('非法操作');
            }

        } elseif ($type == 3) {//焦点图
            $v = (int)$this->getQuery('v');
            $bid = (int)$this->getQuery('bid', 0);
            //todo 限制焦点图 为 5个
            $num = $this->_getPlayIndexBlockTable()->fetchCount(array('link_type' => 2));
            if ($num >= 5 && $v == 2) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '已经有5个焦点图了, 请先取消别的焦点图'));
            }

            $tag = $this->_getPlayIndexBlockTable()->get(array('id' => $bid));
            if (!$tag || $tag->type == 3) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '资讯不能成为焦点图'));
            }

            $stu = $this->_getPlayIndexBlockTable()->update(array('link_type' => $v), array('id' => $bid));
            if ($stu) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
            }
        } elseif ($type == 4) { //刷新 上浮
            $bid = (int)$this->getQuery('bid', 0);
            $t = (int)$this->getQuery('t', 0);
            if ($t) {
                $data = array(
                    'dateline' => time(),
                    'block_order' => 99,
                );
            } else {
                $data = array(
                    'dateline' => time(),
                );
            }
            $stu = $this->_getPlayIndexBlockTable()->update($data, array('id' => $bid));
            if ($stu) {
                return $this->_Goto('成功');
            } else {
                return $this->_Goto('失败');
            }
        } elseif ($type == 5) {
            $title =  $this->getQuery('title');
            $bid = (int)$this->getQuery('bid', 0);
            $stu = $this->_getPlayIndexBlockTable()->update(array('tip' => $title), array('id' => $bid));
            if ($stu) {
                return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
            } else {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '标题未改'));
            }
        } else {
            exit;
        }

    }

    //添加
    public function addAction() {
        $link_id = (int)$this->getPost('id', 0);
        $type = (int)$this->getPost('type', 0);
        $block_city = $_COOKIE['city'];
        $link_type = $this->getPost('real');
        $tip = $this->getPost('tip');
        if($link_type == 1 && !$tip) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '小编说必填'));
        }

        $num = $this->_getPlayIndexBlockTable()->fetchCount(array('link_type' => 2, 'block_city' => $block_city));
        if ($num >= 5 && $link_type == 2) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '已经有>=5个焦点图了, 请先取消别的焦点图'));
        }

        if ($type == 1) {//专题
            $linkData = $this->_getPlayActivityTable()->getActivityList(0, 1, array(), array('play_activity.id' => $link_id))->current();
            $image = $linkData->image;
            $startWhere = array(
                'play_coupons.coupon_status = ?' => 1,
                'play_coupons.coupon_starttime <= ?' => time(),
                'play_coupons.coupon_uptime <= ?' => time(),
                //  '(play_coupons.coupon_total - play_coupons.coupon_buy) > ?' => 0,
                'play_activity_coupon.aid' => $link_id,
                'play_activity_coupon.type' => 'coupon'
            );
            $already = $this->_getPlayActivityCouponTable()->getApiCouponList(0, 100, array(), $startWhere, array());
            $coupon_have = 0;
            $price = NULL;
            foreach ($already as $v) {
                $coupon_have = ($coupon_have || ($v->coupon_join == 1 && $v->coupon_buy < $v->coupon_total && $v->coupon_endtime > time())) ? 1 : 0;
                $price = $price ? (($v->coupon_price < $price) ? $v->coupon_price : $price) : $v->coupon_price;
            }

        } elseif ($type == 2) { //商品
            $linkData = $this->_getPlayCouponsTable()->getCouponsList(0, 1, array(), array('play_coupons.coupon_id' => $link_id))->current();
            $image = $linkData->image;
            //$coupon_have = ($linkData->coupon_join == 1 && $linkData->coupon_buy + $linkData->coupon_vir < $linkData->coupon_total && $linkData->coupon_endtime > time());
            //$price = $linkData->coupon_price;
        } elseif ($type == 3) { //资讯
            $linkData = $this->_getPlayNewsTable()->getAdminNewsList(0, 1, array(), array('play_news.id' => $link_id))->current();
            $image = $linkData->image;
            //$coupon_have = 0;
            //$price = 0;
        } elseif ($type == 4) { //游玩地
            $linkData = $this->_getPlayShopTable()->getAdminShopList(0, 1, array(), array('play_shop.shop_id' => $link_id))->current();
            $image = $linkData->image;
            $couponWhere = array(
                'coupon_status' => 1,
                'play_coupons_linker.shop_id = ?' => $link_id,
            );
            $couponData = $this->_getPlayCouponsTable()->getCouponsList(0, 10, $columns = array(), $couponWhere, $order = array());
            $coupon_have = 0;
            $price = NULL;
            foreach ($couponData as $c_data) {
                $coupon_have = ($coupon_have || ($c_data->coupon_join == 1 && $c_data->coupon_buy + $c_data->coupon_vir < $c_data->coupon_total && $c_data->coupon_endtime > time())) ? 1 : 0;
                $price = $price ? (($c_data->coupon_price < $price) ? $c_data->coupon_price : $price) : $c_data->coupon_price;
            }

        } elseif ($type == 5) { //
            $linkData = $this->_getPlayOrganizerGameTable()->get(array('id' => $link_id));
            $image = '';
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        if (!$linkData) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $flag = $this->_getPlayIndexBlockTable()->get(array('link_id' => $link_id, 'type' => $type, 'block_city' => $block_city));

        if ($flag) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '已推送'));
        }

        $status = $this->_getPlayIndexBlockTable()->insert(array(
                'link_id' => $link_id,
                'type' => $type,
                'block_city' => $block_city,
                'dateline' => time(),
                'editor_image' => $image,
                'link_type' => $link_type,
                'tip' => $tip,
                'block_order' => 99,
               // 'coupon_have' => $coupon_have,
               // 'price' => $price,

            ));

        if ($status) {
            if ($type == 1) {//更新专题的关联价格 及 是否有票
                $this->_getPlayActivityTable()->update(array('reticket' => $coupon_have, 'link_price' => $price),array('id' => $link_id));
            } elseif ($type == 4) {//更新游玩地的关联价格 及 是否有票
                $this->_getPlayShopTable()->update(array('reticket' => $coupon_have, 'link_price' => $price),array('shop_id' => $link_id));
            }

            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }

    }

    public function linkAction() {
        $id = (int)$this->getQuery('lid');
        $type = (int)$this->getQuery('type');
        if (!in_array($type, array(1, 2, 3, 4, 5))) {
            return $this->_Goto('非法操作');
        }
        $data = null;

        if ($type == 4) {
            $data = $this->_getPlayShopTable()->get(array('shop_id' => $id));
            $city = $data->shop_city;
        } elseif ($type == 1) {
            $data = $this->_getPlayActivityTable()->get(array('id' => $id));
            $city = $data->ac_city;
        } elseif ($type == 2) {
            $data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $id));
            $city = $data->coupon_city;
        } elseif ($type == 3) {
            $data = $this->_getPlayNewsTable()->get(array('id' => $id));
            $city = $data->news_city;
        } elseif ($type == 5) {
            $data = $this->_getPlayOrganizerGameTable()->get(array('id' => $id));
            $city = $data->city;
        }

        if (!$data) {
            return $this->_Goto('非法操作');
        }

        $flag = $this->_getPlayIndexBlockTable()->get(array('link_id' => $id, 'type' => $type, 'block_city' => $city));

        if ($flag) {
            return $this->_Goto('已推送到首页');
        }

        $vm = new ViewModel(array(
            'id' => $id,
            'city' => $city,
            'type' => $type,
        ));
        $vm->setTerminal(true);
        return $vm;
    }

}