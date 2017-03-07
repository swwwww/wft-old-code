<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\Seller\Seller;
use Deyi\WeiXinFun;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ViewModel;

class OrganizerController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //活动组织者 详情
    public function indexAction()
    {
        $id = (int)$this->getQuery('id');
        $organizerData = $this->_getPlayOrganizerTable()->get(array('id' => $id, 'status > ?' => 0));
        if (!$organizerData) {
            exit('<h1>活动组织者不存在</h1>');
        }

        //统计活动组织者点击次数
        $this->_getPlayOrganizerTable()->update(array('click_num' => new Expression('click_num+1')), array('id' => $id));

        //基本信息
        $res = array(
            'cover' => $organizerData->cover,
            'name' => $organizerData->name,
            'brief' => $organizerData->brief,
            'information' => $organizerData->information,
            'address' => $organizerData->address,
            'addr_x' => $organizerData->addr_x,
            'addr_y' => $organizerData->addr_y,
            'phone' => $organizerData->phone,
            'organizer_id' => $id,
            'game' => array(),
        );

        //活动
        $gameData = $this->_getPlayOrganizerGameTable()->fetchLimit(0, 3, array(), array('organizer_id' => $id, 'status > ?' => 0, 'start_time < ?' => time(), 'end_time > ?' => time()));
        foreach ($gameData as $gValue) {
            $res['game'][] = array(
                'thumb' => $this->_getConfig()['url'] . $gValue->thumb,
                'id' => $gValue->id,
                'name' => $gValue->title,
                'time' => $gValue->head_time,
                'price' => $gValue->low_price,
                'circle' => $gValue->shop_addr,
            );
        }

        $vm = new viewModel(array(
            'res' => $res,
        ));
        $vm->setTerminal(true);
        return $vm;

    }

    //活动组织者 活动列表
    public function listAction()
    {
        $organizer_id = (int)$this->getQuery('id');
        $time = time();
        $where = array(
            'play_organizer_game.status > ?' => 0,
            'play_organizer_game.start_time < ?' => $time,
            'play_organizer_game.end_time > ?' => $time,
        );
        if ($organizer_id) {
            $where['organizer_id'] = $organizer_id;
        }
        $page = $this->getParams('page', 1);
        $pageSum = 20;
        $gameData = $this->_getPlayOrganizerGameTable()->getAdminGameList(($page - 1) * $pageSum, $pageSum, $columns = array(), $where, $order = array());
        $res = array();
        foreach ($gameData as $gValue) {
            $res[] = array(
                'cover' => $this->_getConfig()['url'] . $gValue->thumb,
                'id' => $gValue->id,
                'title' => $gValue->title,
                'time' => $gValue->head_time,
                'day' => floor(($gValue->foot_time - $gValue->head_time) / 3600),
                'low_price' => $gValue->low_price,
                'large_price' => $gValue->large_price,
                'editor_talk' => $gValue->editor_talk,
                'ticket_num' => $gValue->ticket_num - $gValue->buy_num,
                'organizer' => $gValue->organizer_name === '玩翻天' ? 1 : 0,
                'circle' => $gValue->shop_addr,
            );
        }

        $vm = new viewModel(array(
            'res' => $res,
        ));
        $vm->setTerminal(true);
        return $vm;
    }

//    public function checkPhone($backUrl)
//    {
//        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
//            $url = $this->_getConfig()['url'] . "/web/wappay/bindphone?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
//            header("Location: $url");
//            exit;
//        }
//    }


    //活动详情
    public function gameAction()
    {
        $id = (int)$this->getQuery('id');
        header('location:'.$this->_getConfig()['url'].'/web/organizer/shops?id='.$id);
        exit();
        $gameData = $this->_getPlayOrganizerGameTable()->get(['id' => $id]);


        if ($this->checkWeiXinUser()) {
            $url = $this->_getConfig()['url'] . "/web/organizer/game?id=" . $id;
            $this->checkPhone($url);
        }

        if (!$gameData) {
            exit('<h1>活动不存在</h1>');
        }

        if ($gameData->status == 0) {
            exit('活动未发布');
        }

        $res = [
            'title' => $gameData->title,//商品名称
            'cover' => $this->_getConfig()['url'] . $gameData->cover,//商品封面
            'editor_word' => $gameData->editor_talk,//小玩说
            'age_for' => ($gameData->age_max == 100) ? ($gameData->age_min . '岁及以上') : ($gameData->age_min . '岁到' . $gameData->age_max . '岁'),
            'process' => $gameData->process,
            'information' => htmlspecialchars_decode($gameData->information),
            'limit_num' => $gameData->limit_num,
            'buy' => 0,
            'total_num' => 0,
            'discount' => 10,
            'price' => $gameData->low_price,
            'money' => $gameData->large_price,
            'start_time' => $gameData->start_time,
            'matters' => $gameData->matters,
            'g_buy' => $gameData->g_buy,  //本商品是否参加同玩（团购）：1表示参加，0表示不参加
            'g_price' => $gameData->g_price,//同玩（团购）价格
            'g_limit' => $gameData->g_limit, //同玩（团购）人数
            'coupon_vir' => $gameData->coupon_vir //虚拟票
        ];


        //所有可以下单的类型
        $game_info = $this->_getPlayGameInfoTable()->fetchLimit(0, 200, [], ['gid' => $id, 'status > ?' => 0]);
        foreach ($game_info as $gData) {
            $res['buy'] = $res['buy'] + $gData->buy;
            $res['total_num'] = $res['total_num'] + $gData->total_num;
            $res['price'] = ($gData->price < $res['price']) ? $gData->price : $res['price'];
            $res['money'] = ($gData->money > $res['money']) ? $gData->money : $res['money'];
            $res['discount'] = ($res['discount'] > round($gData->price / $gData->money * 10, 1)) ? round($gData->price / $gData->money * 10, 1) : $res['discount'];
        }

        //活动组织者
        $organizer_data = $this->_getPlayOrganizerTable()->get(['id' => $gameData->organizer_id]);
        $res['organizer'] = [
            'organizer_id' => $organizer_data->id,
            'name' => $organizer_data->name,
            'phone' => $organizer_data->phone,
            'address' => $organizer_data->address,
            'addr_x' => $organizer_data->addr_x,
            'addr_y' => $organizer_data->addr_y
        ];

        //关联的游玩地
        $shop_data = $this->_getPlayGameTimeTable()->getAdminGameShopList(0, 100, [], ['play_game_time.gid' => $id]);
        foreach ($shop_data as $sData) {
            $res['shop'][] = [
                'shop_name' => $sData->shop_name,
                'shop_id' => $sData->shop_id,
                'circle' => $sData->circle,
                'shop_addr' => $sData->shop_address
            ];
        }


        //获取跳转链接
        $toUrl = $this->_getConfig()['url'] . '/web/wappay/activitybuy?id=' . $id;
        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->checkWeiXinUser()) {
            $toUrl = $weixin->getAuthorUrl($toUrl, 'snsapi_userinfo');
        }

        $vm = new viewModel([
            'id' => $id,
            'res' => $res,
            'toUrl' => $toUrl,
            'jsApi' => $weixin->getsignature(),
            'share' => [
                'img' => $this->_getConfig()['url'] . $gameData->thumb,
                'title' => $gameData->title,
                'toUrl' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
            ]
        ]);
        $vm->setTerminal(true);
        return $vm;
    }


    private function getCacheGameOrder($gameData)
    {
        return RedCache::fromCacheData('D:coupon_game_order:' . $gameData->id, function () use ($gameData) {
            $res = array('surplus_num' => 0, 'game_order' => array());
            //精选套系  //所有套系 价格最小的那一个
            $db = $this->_getAdapter();
            $order_data = $db->query("SELECT * FROM play_game_info
  WHERE play_game_info.gid=?  and status=1 AND play_game_info.end_time>?
  AND play_game_info.down_time > ? and play_game_info.total_num > play_game_info.buy ORDER BY play_game_info.price ASC",
                array($gameData->id, time(),time()))->toArray();
            $order_list = array();
            foreach ($order_data as $k => $v) {
                if (!isset($order_list[$v['pid']])) {
                    $order_list[$v['pid']] = $v;
                }

                $order_list[$v['pid']]['surplus_num'] += $v['total_num'] - $v['buy'];//当前套系

                $res['surplus_num'] += $v['total_num'] - $v['buy'];  //总共
            }
            foreach ($order_list as $k => $v) {
                $v = (object)$v;

                if ($gameData->g_buy) {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $gameData->g_set_name ? $gameData->g_set_name : $v->price_name,  //参与方式 time表
                        'price' => $gameData->g_price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 1, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                    );

                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                    );
                    continue;
                } else {
                    $res['game_order'][] = array(
                        'order_id' => $v->id,  //套系id  time表
                        'way' => $v->price_name,  //参与方式 time表
                        'price' => $v->price,
                        'surplus_num' => $v->surplus_num, //剩余
                        'is_group' => 0, // 是否团购
                        'want_score' => $v->integral,//需要的积分才能购买
                    );
                }

            }

            //记录实际剩余数

            RedCache::set('D:SurplusNumber:' . $gameData->id, $res['surplus_num']);
            return $res;
        }, 60, true);
    }

    private function getGameData($id)
    {

            return $this->_getPlayOrganizerGameTable()->get(array('id' => $id));

    }

    private function getCacheData($gameData)
    {

        $cache_data = array('shop' => array(), 'shop_num' => 0, 'shop_id_list' => array(), 'phone' => '', 'welfare' => '', 'game_list' => array());
        $RedName = 'D:coupon_join_data:' . $gameData->id;
        $cache1 = RedCache::get($RedName);
        if ($cache1) {
            return json_decode($cache1, true);
        } else {

            /*************** 绑定的手机号 ******************/
            if (!$gameData->phone) {
                $organizer_data = $this->_getPlayOrganizerTable()->get(array('id' => $gameData->organizer_id));
                $cache_data['phone'] = $organizer_data->phone;
            } else {
                $cache_data['phone'] = $gameData->phone;
            }

            /********************* 关联的游玩地**********************/
            $shop_data = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 100, array(), array('play_game_info.gid' => $gameData->id, 'play_game_info.status >= ?' => 1));
            foreach ($shop_data as $sData) {
                $cache_data['shop_id_list'][] = (int)$sData->shop_id;
                $cache_data['shop'][] = array(
                    'shop_name' => $sData->shop_name,
                    'shop_id' => $sData->shop_id,
                    'circle' => $sData->circle,
                    'address' => $sData->shop_address,
                    'addr_x' => $sData->addr_x,
                    'addr_y' => $sData->addr_y,
                );
            }
            $cache_data['shop_num'] = $this->_getPlayGameInfoTable()->getApiGameShopList(0, 0, array(), array('play_game_info.gid' => $gameData->id, 'play_game_info.status >= ?' => 1))->count();

            /************* 最大返利 **************/
            $welfare = $this->_getPlayWelfareTable()->fetchAll(array('object_id' => $gameData->id, 'object_type' => 2, '(welfare_type=2 or welfare_type=3)', 'status' => 2), array('welfare_value' => 'desc'), 1)->current();
            if ($welfare) {
                $desc = $welfare->welfare_type == 2 ? '现金' : '现金券';
                $res_data = "最高返利{$welfare->welfare_value}元" . $desc;
                $cache_data['get_money'] = $res_data;
            }


            /****** 猜你喜欢 ********/

            //猜你喜欢 相同类别
            $recommend = $this->_getPlayOrganizerGameTable()->fetchAll(array('type' => $gameData->type, 'status' => 1, 'id!=' . $gameData->id, 'city' => $gameData->city), array('dateline' => 'desc'), 3);

            foreach ($recommend as $v) {
                $cache_data['game_list'][] = array(
                    'id' => $v->id,
                    'cover' => $this->_getConfig()['url'] . $v->cover,
                    'name' => $v->title,
                    'price' => $v->low_money
                );
            }
            RedCache::set($RedName, json_encode($cache_data), 60);
            return $cache_data;
        }


    }

    private function getCacheDataConsult($id)
    {

        $cache_data = array('consult' => array());

        $RedName = 'D:coupon_consult:' . $id;
        $cache1 = RedCache::get($RedName);
        if ($cache1) {
            return json_decode($cache1, true);
        } else {
            /****** 咨询列表 ********/
            $consult = $this->_getMdbConsultPost()->find(array('status' => array('$gte' => 1), 'object_data.object_id' => $id))->sort(array('status' => -1, '_id' => -1))->limit(10);
            $cache_data['consult'] = array();
            foreach ($consult as $v) {
                $reply = [];
                if ($v['reply']) {
                    $v['reply']['img'] = 'http://wan.wanfantian.com/uploads/2016/02/26/aca48a6ad9c735fa7edf966bbbe083b1.jpg';
                    $v['reply']['username'] = '小玩';
                    $reply = $v['reply'];
                }
                $cache_data['consult'][] = array(
                    'mid' => (string)$v['_id'],
                    'uid' => $v['uid'],
                    'img' => $this->getImgUrl($v['img']),
                    'dateline' => $v['dateline'],
                    'username' => $this->hidePhoneNumber($v['username']),
                    'msg' => $v['msg'],
                    'reply' => $reply ? array($reply) : array()
                );
            }

            RedCache::set($RedName, json_encode($cache_data), 15);
            return $cache_data;
        }


    }

    //商品详情
    public function shopsAction()
    {
//        $store_url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];



//        RedCache::clearAll();
        $id = (int)$this->getQuery('id');
        $group_id = (int)$this->getQuery('group_id');
        $g_channel = $this->getQuery('g_channel','');
        $seller_id = (int)$this->getQuery('seller_id');
        $uid = $_COOKIE['uid'];

        $Seller = new Seller();

        if ($seller_id) {
            $Seller->shareEffective($seller_id, $uid, $id, 'goods');
        }

        if($g_channel){
            setcookie('g_channel', $g_channel, time() + 3600 * 24 * 1, '/');
            setcookie('g_id', $id, time() + 3600 * 24 * 1, '/');
        }

        $db = $this->_getAdapter();



        $gameData = $this->getGameData($id);

        if (!$gameData or !$gameData->id) {
            exit('<h1>商品不存在</h1>');
        }

        $sid = (int)$this->getQuery('sid',0);
        if($sid>0){
            setcookie('share_order_sn',$sid,time() + 3600 * 24 * 7,'/');
        }

        if($this->checkToken($uid,$_COOKIE['token'])==false){
            unset($_COOKIE['uid'],$_COOKIE['token']);
        }

        $post_url = $this->_getConfig()['url'].'/good/index/nindex';
        $json = $this->post_curl($post_url,array('id'=>$id),$_COOKIE['sel_city'],$_COOKIE);
        $data = json_decode($json,true);
        $res = $data['response_params'];
        if($group_id){
            $game_info_id = $this->_getPlayGroupBuyTable()->get(array('id'=>$group_id))->game_info_id;
            $user_group_buy_info = $this->_getPlayOrderInfoTable()->get(array('user_id'=>$uid,'group_buy_id'=>$group_id));
            if($user_group_buy_info){
                $info['user_group_buy_status']=1;
            }
            $info['group_id'] = $group_id;
            $group_data = $this->_getPlayGroupBuyTable()->get(array('id' => $group_id,'game_id'=>$id));
            if(!$group_data){
                exit('<h1>拼团信息不存在</h1>');
            }
            $info['group_price'] = $gameData->g_price;
            $info['not_group_price'] = $this->_getPlayGameInfoTable()->get(array('id'=>$game_info_id))->price;
            $info['group_info'] = array();
            $info['group_game_info_pid'] = $this->_getPlayGameInfoTable()->get(array('id'=>$group_data->game_info_id))->pid;
            $info['group_join_number'] = $group_data->join_number;
            $info['group_limit_number'] = $group_data->limit_number;
            $info['group_end_time'] = $group_data->end_time;
            $info['group_status'] = $group_data->status;
            $info['group_user'] = $group_data->uid;
            $info['group_game_info_id'] = $group_data->game_info_id;
//            $data = $this->_getPlayOrderInfoTable()->groupBuyImgList($group_id);
            $data = $db->query("select play_user.username,play_user.img,play_order_info.user_id from play_order_info left join play_user on play_user.uid=play_order_info.user_id where play_order_info.group_buy_id=? and order_status=1",array($group_id))->toArray();

            $list = array();
            foreach ($data as $v) {
                $list[] = array('username' => $v['username'], 'userimg' => $this->getImgUrl($v['img']), 'uid' => $v['user_id']);
            }
            $info['group_info'] = $list;
        }

        $res['id'] = $id;
        $res['information'] = htmlspecialchars_decode($gameData->information);
        $res['group_id']=$info['group_id'];
        $res['not_group_price']=$info['not_group_price'];
        $res['group_price']=$info['group_price'];
        $res['group_info']=$info['group_info'];
        $res['group_game_info_pid']=$info['group_game_info_pid'];
        $res['group_join_number']=$info['group_join_number'];
        $res['group_limit_number']=$info['group_limit_number'];
        $res['group_end_time']=$info['group_end_time'];
        $res['group_user']=$info['group_user'];
        $res['group_game_info_id']=$info['group_game_info_id'];
        $res['group_status']=$info['group_status'];
        $res['user_group_buy_status']=$info['user_group_buy_status'];
        $res['is_private']=$gameData->is_private_party;

        //获取该商品的可参团信息
        $group_data = $db->query("select play_group_buy.*,play_user.img from play_group_buy left join play_user on play_user.uid=play_group_buy.uid left join play_order_info on play_order_info.group_buy_id=play_group_buy.id where play_group_buy.status=1 and play_group_buy.end_time>? and play_group_buy.game_id={$id} and play_order_info.pay_status >=2 and play_order_info.order_status=1 limit 4",array(time()))->toArray();
        $group_num = $db->query("select count(play_group_buy.id) as num from play_group_buy left join play_user on play_user.uid=play_group_buy.uid left join play_order_info on play_order_info.group_buy_id=play_group_buy.id where play_group_buy.status=1 and play_group_buy.end_time>? and play_group_buy.game_id={$id} and play_order_info.pay_status >=2 and play_order_info.order_status=1",array(time()))->current();
        foreach($group_data as $v){
            $v['img'] = $this->getImgUrl($v['img']);
        }
        $res['group_num'] = $group_num->num;
        $res['group_data'] = $group_data;

        //用户该商品是否参团和结团
        if($gameData->g_buy==1){
            $group_order_data = $db->query("select play_group_buy.*,play_order_info.group_buy_id from play_order_info left join play_group_buy on play_group_buy.id=play_order_info.group_buy_id where user_id=? and coupon_id=? and play_group_buy.end_time>? and pay_status>=2 and order_status=1 order by play_group_buy.end_time desc",array($uid,$id,time()))->toArray();

            if($group_order_data){
                $res['user_group_info'] = $group_order_data;
            }
        }

        //判断最外层购买状态
        $sell_status = 0;
        $qualify_status = 0;
        $new_status = 0;
        $start_status = 0;
        foreach($res['game_order'] as $v){
//            var_dump($v);
            if($v['buy_qualify']==1 && $v['new_user_buy']<2 && $v['up_time']<time() && $v['down_time']>time() && $v['surplus_num']>0){
                $sell_status +=1;
            }
            if($v['up_time']>time()){
                $start_status +=1;
            }
            if($v['down_time']>time() and $v['buy_qualify']==0 and $v['surplus_num']>0){
                $qualify_status +=1;
            }
            if($v['new_user_buy']==2){
                $new_status += 1;
            }
        }

        $res['sell_status']=$sell_status;
        $res['new_status']=$new_status;
        $res['qualify_status']=$qualify_status;
        $res['start_status']=$start_status;
        //分享
        $jswxconfig = $this->getShareInfoAction()[1];

        $is_right = $Seller->isRight($uid);
        if ($is_right) {
            $Url = $this->_getConfig()['url'] . "/web/organizer/shops?id={$id}&seller_id={$uid}";
        } else {
            $Url = $this->_getConfig()['url'] . "/web/organizer/shops?id={$id}";
        }

        $share = array(
            'img'=>$this->_getConfig()['url'] . $gameData->thumb,
            'title'=>'【玩翻天】'.$gameData->title,
            'desc'=> '我发现了一个不错的商品，'.$res['game_order'][0]['price'].'元起，我们一起报名吧。'.str_replace(array(" ","　","\t","\n","\r"),'',$gameData->editor_talk),
            'link'=> $Url,
        );
        $vm = new viewModel([
            'res' => $res,
            'jsconfig'=>$jswxconfig,
            'share'=>$share,
            'share_type'=>'game',
            'share_id'=>$id
        ]);
        $vm->setTerminal(true);
        return $vm;

    }

    //拼团列表详情
    public function groupruleAction(){
        $db = $this->_getAdapter();
        $id = (int)$this->getQuery('id',0);//商品id
        if(!$id){
            return $this->_Goto("参数错误");
        }

        $good_data = $this->_getPlayOrganizerGameTable()->get(array('id'=>$id));
        $group_data = $db->query("select play_user.username as name,play_user.img,play_group_buy.* from play_group_buy left join play_user on play_user.uid=play_group_buy.uid where play_group_buy.game_id=? and play_group_buy.status=1 order by join_number desc,end_time desc",array($id))->toArray();
        $res = array(
            'title'=>$good_data->title,
            'id'=>$id,
            'price'=>$good_data->g_price
        );
        foreach($group_data as $v){
            $v['img'] = $this->_getConfig()['url'].$v['img'];
            $res['group_data'][] = $v;
        }
        $vm = new viewModel([
            'res' => $res,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }
    //点评
    public function reviewAction()
    {
        $id = (int)$this->getQuery("id",0);
        $type = (int)$this->getQuery('type',0);
        $order_sn = (int)$this->getQuery('order_sn',0);
        $url = $this->_getConfig()['url']."/web/organizer/shops?id={$id}";
        $vm = new viewModel([
            'id'=>$id,
            'type'=>$type,
            'order_sn'=>$order_sn
        ]);
        $vm->setTerminal(true);
        return $vm;
//        $this->WeiXinArc($url);
    }

    public function infoAction()
    {
        $rid = (int)$this->getQuery('rid');
        $gid = (int)$this->getQuery('gid');
        $cid = (int)$this->getQuery('cid');
        $pid = (int)$this->getQuery('pid');
        $sid = (int)$this->getQuery('sid'); //攻略ID
        $eid = (int)$this->getQuery('eid'); //攻略ID
        $type = $this->getQuery('type');
        $information = '';

        if ($type == 1) { //活动 图文详情
            $gameData = $this->_getPlayOrganizerGameTable()->get(['id' => $gid]);
            $information = htmlspecialchars_decode($gameData->information);
        }
        if ($type == 2) {
            $organizerData = $this->_getPlayOrganizerTable()->get(['id' => $rid]);
            $information = htmlspecialchars_decode($organizerData->information);
        }

        if ($type == 3) {
            $couponData = $this->_getPlayCouponsTable()->get(['coupon_id' => $cid]);
            $information = htmlspecialchars_decode($couponData->coupon_description);
        }
        //游玩地详情
        if ($type == 4 && $pid) {
            $placeData = $this->_getPlayShopStrategyTable()->fetchLimit(0, 1, array(), array('sid' => $pid, 'status > 0'), ['status' => 'desc'])->current();
            if (!$placeData) {
                $placeData = $this->_getPlayShopTable()->get(array('shop_id' => $pid));
            }

            $information = htmlspecialchars_decode($placeData->information);
        }
        //游玩地攻略
        if ($type == 4 && $sid) {
            $placeData = $this->_getPlayShopStrategyTable()->get(['id' => $sid]);

            $information = htmlspecialchars_decode($placeData->information);
        }

        if ($type == 5 && $eid) {
            $excerciseData = $this->_getPlayExcerciseBaseTable()->get(['id' => $eid]);

            $information = htmlspecialchars_decode($excerciseData->highlights);
        }

        $vm = new viewModel([
            'res' => $information,
            'type' => $type
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    /**
     * 商品详情web版，同玩规则
     * @return ViewModel
     */
    public function ruleAction()
    {
        $type = (int)$this->getQuery('type');
        $gid = (int)$this->getQuery('gid');
        $uid = (int)$this->getQuery('uid');
        if (!in_array($type, array(1, 2, 3, 4))) {
            exit('你走进了一个迷宫哦');
        }

        $data = array(
            'img' => '',
            'end_time' => '',
            'people' => '',
        );

        if ($type == 2) {
            $groupData = $this->_getPlayGroupBuyTable()->get(array('id' => $gid));
            if (!$groupData) {
                exit('你走进了一个迷宫哦!');
            }
            $data['end_time'] = $groupData->end_time;
            $data['people'] = $groupData->limit_number - $groupData->join_number;
        }

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if ($userData) {
            $data['img'] = $this->getImgUrl($userData->img);
        }

        $vm = new viewModel($data);
        $vm->setTemplate('web/organizer/rule_' . $type . '.phtml');
        return $vm;
    }

    public function checkPhone($backUrl)
    {
        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
            //临时查询用户是否已绑定手机号
            $user_data = $this->_getPlayUserTable()->get(array('uid' => (int)$_COOKIE['uid']));
            if ($user_data->phone) {
                $untime = time() + 3600 * 24 * 17;  //失效时间
                setcookie('phone', $user_data->phone, $untime, '/');
                return true;
            } else {
                $url = $this->_getConfig()['url'] . "/web/wappay/register?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
                header("Location: $url");
                exit;
            }

        }
    }
}
