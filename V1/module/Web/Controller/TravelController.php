<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\JsonResponse;
use Deyi\WeiXinFun;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class TravelController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function travellerAction(){
        $people_num = (int)$this->getQuery("total_people_num");
        $order_sn = $this->getQuery("order_sn");
        $ids = $this->getQuery("ids");
        $good_num = (int)$this->getQuery("good_num");
        $type = (int)$this->getQuery("type");//1 套系选择  2 个人中心  3订单出行人完成
        $tip = (int)$this->getQuery("tip");//1 活动
        $city = urldecode($this->getQuery("city"));
        $uid = $_COOKIE['uid'];
//        $_COOKIE['open_id']='oBzdouDW7_CB-Sbby1dlk9bu-ebE';
//        $_COOKIE['token']='098024166cdb0828804a45423b7e5967';
//        $_COOKIE['uid']='70623';
//        $_COOKIE['phone']='13871281565';
        $url = $this->_getConfig()['url']."/user/associates/list";
        if($type==3){
            $ids = $this->getQuery("ids");
            $data = $this->_getPlayUserAssociatesTable()->fetchAll("associates_id in ($ids)")->toArray();
        }else{
            $json = $this->post_curl($url,array("uid"=>$uid),$city,$_COOKIE);
            $obj = json_decode($json,true);
            $data = $obj['response_params'];
        }

        $vm = new ViewModel([
            'data'=>$data,
            'people_num'=>$people_num,
            'type'=>$type,
            'tip'=>$tip,
            'ids'=>$ids,
            'order_sn'=>$order_sn,
            'good_num'=>$good_num,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    public function updatetravellerAction(){
        $id = (int)$this->getQuery("id");
        if($id>0){
            $data = $this->_getPlayUserAssociatesTable()->get(array('associates_id'=>$id));
        }

        $vm = new ViewModel([
            'data'=>$data,
        ]);
        $vm->setTerminal(true);
        return $vm;
    }
    public function editaddrAction(){
        $id = (int)$this->getQuery("id");
        $type = (int)$this->getQuery("type",0);
        if($id>0){
            $addr_data = $this->_getPlayUserLinkerTable()->get(array('linker_id' => $id));
        }

        $vm = new ViewModel([
            'data'=>$addr_data,
            'type'=>$type
        ]);
        $vm->setTerminal(true);
        return $vm;
    }

    public function addrlistAction(){
        $uid = $_COOKIE['uid'];
        $type = (int)$this->getQuery("type",0);
        $url = $this->_getConfig()['url'].'/web/wap/addrlist';
        $weixin = new WeiXinFun($this->getwxConfig());
        if(!$uid){
            //todo 授权失败
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        $post_url = $this->_getConfig()['url'].'/user/phone';
        $json = $this->post_curl($post_url,array('uid'=>$uid),"武汉",$_COOKIE);
        $data = json_decode($json,true);
        $vm = new ViewModel([
            'data'=>$data['response_params'],
            'type'=>$type
        ]);
        $vm->setTerminal(true);
        return $vm;
    }


}
