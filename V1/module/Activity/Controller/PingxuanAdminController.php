<?php

namespace Activity\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Account\Account;
use Deyi\Mcrypt;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\WriteLog;
use library\Service\System\Cache\RedCache;
use Zend\Db\Sql\Expression;
use Zend\Db\TableGateway\TableGateway;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\EventManager\EventManagerInterface;

class PingxuanAdminController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {

    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            if ($_SERVER['REQUEST_URI'] != '/activity/manage/login') { //排除登陆界面
                if (isset($_COOKIE['user'])) {
                    $hash_data = json_encode(array('user' => $_COOKIE['user'], 'id' => $_COOKIE['id'], 'group' => $_COOKIE['group']));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    if ($token == $_COOKIE['token']) {
                    } else {
                        header('Location: /activity/manage/login');
                        exit;
                    }
                } else {
                    header('Location: /activity/manage/login');
                    exit;
                }
            }

        }, 100);
    }

    //刷新上浮
    public function UpAction(){
        $id=$this->getQuery('id');
        $s=$this->getBusinesstable()->update(array('sort'=>time()),array('id'=>$id));
        if($s){
            return $this->_Goto('操作成功');
        }else{
            return $this->_Goto('操作失败');
        }
    }

    //商家列表
    public function BusinessAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();

        $data = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE id > ? ORDER BY sort DESC LIMIT {$start}, {$pageSum}", array(0));

        $count = $adapter->query("SELECT * FROM activity_pingxuan_business WHERE id > ?", array(0))->count();

        //创建分页
        $url = '/activity/pingxuanadmin/business';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));
        return $vm;
    }

    //添加商家
    public function editBusinessAction()
    {
        if ($_GET['id']) {
            return array(
                'data' => $this->getBusinesstable()->select(array('id' => $_GET['id']))->current()
            );
        }
    }

    //保存商家
    public function saveBusinessAction()
    {
//        var_dump($_POST);exit;
        unset($_POST['file']);

        if ($_POST['id']) {//update

            $a = $this->getBusinesstable()->update($_POST, array('id' => $_POST['id']));

            if ($a) {
                return $this->_Goto('修改成功');
            } else {
                return $this->_Goto('修改失败');
            }
        } else {
            unset($_POST['id']);

            $a = $this->getBusinesstable()->insert($_POST);

            if ($a) {
                return $this->_Goto('添加成功');
            } else {
                return $this->_Goto('添加失败');
            }

        }

    }

    //删除商家
    public function delBusinessAction()
    {
        $id = $_GET['id'];

        if ($id) {
            $a = $this->getBusinesstable()->delete(array('id' => $id));
            if ($a) {
                return $this->_Goto('删除成功');
            }
        }
        return $this->_Goto('删除失败');

    }


    //用户列表
    public function userlistAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $start = ($page - 1) * $pageSum;


        $data=$this->getUsertable()->select(function($where)use($start,$pageSum){
            $where->offset($start)->limit($pageSum);
        });

        $count = $this->getUsertable()->select()->count();

        //创建分页
        $url = '/activity/pingxuanadmin/userlist';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));

        return $vm;



    }

    //显示用户奖品
    public function userprizeAction()
    {


        $page = (int)$this->getQuery('p', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $start = ($page - 1) * $pageSum;
        $unionid=$this->getQuery('unionid');

        if (!$unionid) {
            return $this->_Goto('非法操作');
        }


        $data=$this->getPrizetable()->select(function($where)use($start,$pageSum,$unionid){

            $where->join('activity_pingxuan_prize_data','activity_pingxuan_prize_data.id=activity_pingxuan_prize.prize_id',array('name'),'left');

            $where->where(array('unionid'=>$unionid));
            $where->offset($start)->limit($pageSum);
        });
        $adapter = $this->_getAdapter();
        $count = $adapter->query("SELECT * FROM activity_pingxuan_prize WHERE unionid = ?", array($unionid))->count();

        //创建分页
        $url = '/activity/pingxuanadmin/userlist';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));

        return $vm;
    }

    //显示用户投票记录 可以导出
    public function prizelogAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $start = ($page - 1) * $pageSum;
        $unionid=$this->getQuery('unionid');


        $data=$this->getVoteLogtable()->select(function($where)use($start,$pageSum,$unionid){

            $where->join('activity_pingxuan_business','activity_pingxuan_business.id=activity_pingxuan_vote_log.bid',array('name'),'left');

            $where->where(array('unionid'=>$unionid));
            $where->offset($start)->limit($pageSum);
        });

        $count = $this->getVoteLogtable()->select()->count();

        //创建分页
        $url = '/activity/pingxuanadmin/userlist';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
        ));

        return $vm;



    }

    /*设置奖品操作*/

    //奖品列表
    public function prizeListAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = (int)$this->getQuery('page_num', 10);
        $start = ($page - 1) * $pageSum;
        $adapter = $this->_getAdapter();

        $data = $adapter->query("SELECT * FROM activity_pingxuan_prize_data WHERE status = ? ORDER BY id DESC LIMIT {$start}, {$pageSum}", array(1));

        $count_data = $adapter->query("SELECT count(id) as num, SUM(probability) as pv FROM activity_pingxuan_prize_data WHERE status = ?", array(1))->current();

        $count = $count_data['num'];
        $pv = $count_data['pv'];
        //创建分页
        $url = '/activity/pingxuanadmin/prizeList';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'data' => $data,
            'page' => $paging->getHtml(),
            'pv' => $pv,
        ));
        return $vm;
    }

    //添加奖品
    public function newPrizeAction() {
        $data = NULL;
        if ($_GET['id']) {

            $id = (int)$_GET['id'];
            $adapter = $this->_getAdapter();
            $data = $adapter->query("SELECT * FROM  activity_pingxuan_prize_data WHERE id = ?", array($id))->current();
        }

        return array(
            'data' => $data,
        );
    }

    //保存奖品
    public function savePrizeAction() {
        unset($_POST['file']);
        $no_prize_id = 1;

        if ($_POST['id']) {//update

            if ($_POST['probability'] > 1000 && $_POST['id'] != $no_prize_id) {
                return $this->_Goto('概率不能超过1000');
            }

            $a = $this->getPrizeDatatable()->update($_POST, array('id' => $_POST['id']));

            if ($a) {
                return $this->_Goto('修改成功', '/activity/pingxuanadmin/prizeList');
            } else {
                return $this->_Goto('修改失败');
            }
        } else {
            unset($_POST['id']);

            if ($_POST['probability'] > 1000) {
                return $this->_Goto('概率不能超过1000');
            }

            $a = $this->getPrizeDatatable()->insert($_POST);

            if ($a) {
                return $this->_Goto('添加成功', '/activity/pingxuanadmin/prizeList');
            } else {
                return $this->_Goto('添加失败');
            }

        }
    }

    //删除奖品
    public function deletePrizeAction() {
        $id = (int)$_GET['id'];

        if ($id) {
            $adapter = $this->_getAdapter();
            $result = $adapter->query("UPDATE activity_pingxuan_prize_data SET status = ? WHERE id = ?", array(0, $id))->count();

            if ($result) {
                return $this->_Goto('删除成功');
            }
        }
        return $this->_Goto('删除失败');
    }



    /********* 数据表 *********/
    //商家表
    public function getBusinesstable()
    {

        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_business', $Adapter);
    }

    //用户表
    public function getUsertable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_user', $Adapter);
    }

    //投票记录表
    public function getVoteLogtable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_vote_log', $Adapter);
    }

    //奖品表
    public function getPrizeDatatable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_prize_data', $Adapter);
    }

    //中奖记录表
    public function getPrizetable()
    {
        $Adapter = $this->_getAdapter();
        return new TableGateway('activity_pingxuan_prize', $Adapter);
    }

}
