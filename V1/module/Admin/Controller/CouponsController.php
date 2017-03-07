<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\ImageProcessing;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CouponsController extends AbstractActionController
{
    use JsonResponse;
    //use BaseController;

    //卡券列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pagesum = 10;
        $like = $this->getQuery('k', '');
        $id = $this->getQuery('id');
        $city = $this->getQuery('city', 'WH');
        $where = array(
            'coupon_city = ?' => $city,
        );
        if ($like) {
            $where['coupon_name like ?'] = '%' . $like . '%';
        }
        if ($id) {
            $where['play_coupons.coupon_id'] = $id;
        }
        //状态
        $status = $this->getQuery('status');
        if ($status) {
            if ($status == 1) {
                //$where['coupon_endtime < ?'] = time();
                //$where['coupon_status > ?'] = -1;

                $where["coupon_status > ? && ((coupon_endtime < ? )|| (coupon_total = coupon_buy) )"] = array(0, time());
            } elseif ($status == 2) {
                $where['coupon_status = ? && coupon_total > coupon_buy  && coupon_endtime > ? && coupon_starttime < ?'] = array(1, time(), time());
            } elseif ($status == 3) {
                $where['coupon_status = ?'] = 0;
            } elseif ($status == 4) {
                $where['coupon_uptime < ?'] = time();
                $where['coupon_starttime > ?'] = time();
                $where['coupon_status > ?'] = 0;
            } elseif ($status == 5) {
                $where['coupon_uptime > ?'] = time();
                $where['coupon_status > ?'] = 0;
            }
        } else {
            $where['coupon_status > ?'] = -1;
        }

        //从黑屋出来
        if ($this->getQuery('heiwu')) {
            $where['coupon_status > ?'] = -3;
        }

        //商家下的卡券
        $mid = $this->getQuery('mid');
        if ($mid) {
            $where['coupon_marketid = ?'] = $mid;
        }
        //店铺下的卡券
        $sid = $this->getQuery('sid');
        if ($sid) {
            $where['play_coupons_linker.shop_id = ?'] = $sid;
        }
        $start = ($page - 1) * $pagesum;
        $data = $this->_getPlayCouponsTable()->getCouponsList($start, $pagesum, array(), $where, array('coupon_dateline' => 'desc'));
        //获得总数量
        $count = $this->_getPlayCouponsTable()->getCouponsList(0, 0, array(), $where, array('coupon_dateline' => 'desc'))->count();
        //创建分页
        $url = '/wftadlogin/coupons';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'cityData' => $this->_getConfig()['city'],
            'city' => $city,
        );
    }


    public function newAction()
    {
        $cid = (int)$this->getQuery('id');
        $mid = (int)$this->getQuery('mid');
        $city = $this->getQuery('city', 'WH');

        if (!$mid) {
            return $this->_Goto('非法入口');
        }
        $marketData = $this->_getPlayMarketTable()->get(array('market_id' => $mid));
        $shops = $this->_getPlayShopTable()->fetchAll(array('shop_mid' => $mid, 'shop_status >= ?' => 0));
        $data = array();
        if ($cid) {
            $data = $this->_getPlayCouponsTable()->get(array('coupon_id' => $cid));
        }

        $vm = new viewModel(
            array(
                'data' => $data,
                'marketData' => $marketData,
                'couponType' => $this->_getConfig()['coupon_type'],
                'shops' => $shops,
                'city' => array(
                    'mark' => $city,
                    'name' => $this->_getConfig()['city'][$city],
                ),
            )
        );
        return $vm;
    }

    public function saveAction()
    {
        $coupon_id = (int)$this->getPost('coupon_id');
        $coupon_close = strtotime($this->getPost('coupon_close') . $this->getPost('coupon_closel'));
        $coupon_starttime = strtotime($this->getPost('coupon_starttime') . $this->getPost('coupon_starttimel'));
        $coupon_endtime = strtotime($this->getPost('coupon_endtime') . $this->getPost('coupon_endtimel'));
        $coupon_uptime = strtotime($this->getPost('coupon_uptime') . $this->getPost('coupon_uptimel'));
        $refund_time = strtotime($this->getPost('refund_time') . $this->getPost('refund_timel'));
        $coupon_name = $this->getPost('coupon_name');
        $coupon_typename = $this->getPost('coupon_typename');
        $coupon_marketname = $this->getPost('coupon_marketname');
        $coupon_marketid = $this->getPost('coupon_marketid');
        $coupon_originprice = $this->getPost('coupon_originprice');
        $coupon_price = $this->getPost('coupon_price');
        $coupon_total = $this->getPost('coupon_total');
        $coupon_limitnum = (int)$this->getPost('coupon_limitnum');
        $coupon_vir = $this->getPost('coupon_vir');
        $coupon_appointment = $this->getPost('coupon_appointment');
        $coupon_share = $this->getPost('coupon_share');
        $coupon_description = $this->getPost('editorValue');
        $allow_post = $this->getPost('allow_post');
        $coupon_join = $this->getPost('coupon_join');
        $coupon_remind = $this->getPost('coupon_remind');
        //$refund = $this->getPost('refund');

        $editor_word = $this->getPost('editor_word');
        $use_time = $this->getPost('use_time');
        $age_min = $this->getPost('age_min');
        $age_max = $this->getPost('age_max');
        $attend_method = $this->getPost('attend_method');
        $matters_attention = $this->getPost('matters_attention');
        //$use_info = $this->getPost('use_info');
        $new_user = $this->getPost('new_user');

        if ($coupon_join == 0) {
            $coupon_originprice = $coupon_price;
            $coupon_share = 1;
            $new_user = 0;
            $coupon_appointment = 1;
            $coupon_total = 0;
            $coupon_limitnum = 0;
            $coupon_vir = 0;
            $coupon_close = 1426515428;
            $coupon_starttime = 1426515428;
            $coupon_endtime = 1426515428;
            $coupon_uptime = 1426515428;

        }

        if (!$refund_time) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '最后退款时间不能为空'));
        }

        $coupon_cover = $this->getPost('coverset');
        if (!$coupon_cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '封面图片'));
        }


        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $coupon_cover);
        $cover_status = $cover_class->scaleResizeImage(720,360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $coupon_cover);
        }

        $coupon_thumb = $this->getPost('coupon_thumb') ? $this->getPost('coupon_thumb') : $this->getPost('coverset');
        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $coupon_thumb);
        $surface_status = $surface_class->scaleResizeImage(360,360);
        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $coupon_thumb);
        }


        if (strlen($attend_method) > 750) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '使用说明超出了250字数'));
        }
        if (strlen($matters_attention) > 750) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '注意事项超出了250字数'));
        }
        if (strlen($editor_word) > 750) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '小编说超出了250字数'));
        }



        if (!$coupon_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券名称'));
        }

        if ($coupon_join != 0) {
            if (!$coupon_originprice) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券原价'));
            }
            if (!$coupon_price) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券价格'));
            }
            if ($coupon_originprice < $coupon_price) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '现价大于原价'));
            }
        }


        if ($coupon_join != 0 && !$coupon_total) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券份数'));
        }
        if ($coupon_limitnum > $coupon_total) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '每机限购 不能大于共多少张'));
        }
        if (!$coupon_description) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '图文说明'));
        }
        if (!$coupon_cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券封面'));
        }
        if ($coupon_join != 0 && $coupon_endtime <= $coupon_starttime) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开始时间》= 结束时间'));
        }

        if ($coupon_join != 0 && $coupon_close <= $coupon_endtime) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券使用截止时间需大于下架时间'));
        }

        if ($coupon_join != 0 && $coupon_uptime >= $coupon_starttime) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '卡券开始时间需大于上架架时间'));
        }
        if ($age_max < $age_min) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '年龄选择不对'));
        }

        $coupon_shopids = $this->params()->fromPost('shopIds');
        if (!$coupon_shopids) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '最少选择一个店铺'));
        }
        $coupon_shopid = json_encode($coupon_shopids);

        $data = array(
            'coupon_close' => $coupon_close,
            'coupon_starttime' => $coupon_starttime,
            'coupon_endtime' => $coupon_endtime,
            'coupon_uptime' => $coupon_uptime,
            'coupon_name' => $coupon_name,
            'coupon_typename' => $coupon_typename,
            'coupon_marketname' => $coupon_marketname,
            'coupon_marketid' => $coupon_marketid,
            'coupon_originprice' => $coupon_originprice,
            'coupon_price' => $coupon_price,
            'coupon_total' => $coupon_total,
            'coupon_limitnum' => $coupon_limitnum,
            'coupon_vir' => (int)$coupon_vir,
            'coupon_appointment' => $coupon_appointment,
            'coupon_share' => $coupon_share,
            'coupon_description' => $coupon_description,
            'coupon_cover' => $coupon_cover,
            'coupon_thumb' => $coupon_thumb,
            'coupon_dateline' => time(),
            'coupon_shopid' => $coupon_shopid,
            'editor_word' => $editor_word,
            'editor_id' => $_COOKIE['id'],
            'use_time' => $use_time,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'attend_method' => $attend_method,
            'matters_attention' => $matters_attention,
            //'use_info' => $use_info,
            'new_user' => $new_user,
            'allow_post' => $allow_post,
            'coupon_remind' => $coupon_remind,
            'coupon_join' => $coupon_join,
            'refund_time' => $refund_time,
        );

        $copId = 0;
        if ($coupon_id) {
            $status = $this->_getPlayCouponsTable()->update($data, array('coupon_id' => $coupon_id));
        } else {
            //$data['refund'] = $refund;
            $status = $this->_getPlayCouponsTable()->insert($data);
            $copId = $this->_getPlayCouponsTable()->getlastInsertValue();
            $coupon_id = $copId;
        }

        /******** 关联店铺表 *********/
        if ($coupon_id) {
            $this->_getPlayCouponsLinkerTable()->delete(array('coupon_id' => $coupon_id));
        }

        if ($coupon_shopids) {
            foreach ($coupon_shopids as $val) {
                $this->_getPlayCouponsLinkerTable()->insert(array('coupon_id' => $coupon_id, 'shop_id' => $val));
            }
            //合作卡券 改变游玩地合作状态
            if ($coupon_join == 1) {
                $this->_getPlayShopTable()->update(array('shop_type' => 1), array('shop_id' => $val));
            }
        }
        /******** 关联店铺表表结束 *********/

        if ($status) {
            /*** 发送站内消息 ****/
            if (count($coupon_shopids) && $copId) {
                $this->sendMes($copId, $coupon_name, $coupon_shopids);
            }
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
    }

    public function deleteAction()
    {
        // todo 待定
        $cid = (int)$this->getQuery('cid');
        $this->_getPlayCouponsTable()->update(array('coupon_status' => -1), array('coupon_id' => $cid));
        return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功'));
    }

    public function changeAction()
    {
        $type = $this->getQuery('type');

        if ($type == 1) { //改变发布状态
            $cid = (int)$this->getQuery('cid');
            $stu = (int)$this->getQuery('stu');
            $this->_getPlayCouponsTable()->update(array('coupon_status' => $stu), array('coupon_id' => $cid));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功'));
        } elseif ($type == 2) {
            $cid = (int)$this->getQuery('cid');
            $this->_getPlayCouponsTable()->update(array('coupon_dateline' => time()), array('coupon_id' => $cid));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '操作成功'));
        } else {
            return $this->_Goto('非法入口');
        }
    }


    private function sendMes($coupon_id, $coupon_name,$coupon_shopids) {
        $title = '新优惠';
        $data = array();
        $timer = time();
        foreach($coupon_shopids as $sid) {
            $coll_data = $this->_getPlayUserCollectTable()->getAdminCollectShopName(0, 100000, array(), array('play_user_collect.type' => 'shop', 'play_user_collect.link_id' => $sid));
            if ($coll_data->count()) {
                foreach ($coll_data as $us_data) {
                    if (array_key_exists($us_data->uid, $data)) {
                        $data[$us_data->uid] = array(
                            'uid' => $us_data->uid,
                            'type' => 1,
                            'title' => $title,
                            'deadline' => $timer,
                            'message' => "您关注的游玩地有新的优惠:{$coupon_name}，快带小宝贝飞奔而来吧。",
                            'link_id' => $coupon_id,
                            'link_data' => $sid
                        );
                    } else {
                        $data[$us_data->uid] = array(
                            'uid' => $us_data->uid,
                            'type' => 1,
                            'title' => $title,
                            'deadline' => $timer,
                            'message' => "您关注的游玩地{$us_data->shop_name}有新的优惠:{$coupon_name}，快带小宝贝飞奔而来吧。",
                            'link_id' => $coupon_id,
                            'link_data' => $sid
                        );
                    }
                }
            }
        }

        $this->_getPlayMessagePushTable()->insert(array('type' => 'coupon', 'lid' => $coupon_id, 'deadline' => $timer));

        //发送站内消息
        $i = 1;
        $sql = "INSERT INTO `play_user_message` (`id`, `uid`, `status`, `type`, `title`, `message`, `deadline`, `is_new`, `link_id`, `link_data` ) VALUES";
        foreach ($data as $val) {
            if ($i == 1) {
                $sql=$sql."(NULL ,".$val['uid'].", 1, 1,'{$val['title']}', '{$val['message']}', ".$val['deadline'].", 1, '{$val['link_id']}', '{$val['link_data']}')";
            } else {
                $sql=$sql.", (NULL ,".$val['uid'].", 1, 1,'{$val['title']}', '{$val['message']}', ".$val['deadline'].", 1, '{$val['link_id']}', '{$val['link_data']}')";
            }
            $i++;
        }
        $status = $this->query($sql)->count();

        return $status;
    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute();
        return $result;
    }
}
