<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Deyi\BaseController;
use Deyi\PHPQrCode\QrCode;
use library\Service\System\Cache\RedCache;
use Deyi\SendMessage;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;

class DituiController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //领奖页面
    public function indexAction()
    {
        $db = $this->_getAdapter();


        $acid = (int)$this->getQuery('acid', 0);//活动id
        $param = $this->getWapUid(true);

        $uid = $param['uid'];
        $mid = $param['mid'];
//        var_dump($param);


        if(!$this->is_wft() or  !isset($_GET['p'])){
            header("Location: http://wan.wanfantian.com/app/index.php");
        }


        if (!$uid or !$mid or !$acid) {
            $data = <<<HTML
<html>
<head>
    <title>提示</title>
</head>
<body>
<h3 style="text-align: center;padding-top: 50px;">请登录后使用APP扫码领取</h3>
</body>
<script>
//if (navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iphone os/i) || navigator.userAgent.match(/ipod/i)) {
//   document.location.href = 'reg_user$$';
//} else if (navigator.userAgent.match(/android/i)) {
//    window.getdata.reg_user()
//}
</script>
</html>
HTML;
            echo $data;
            exit;
        }

        $data = array(
            'get_gift' => 0,//是否已领取
            'can_get' => 0,//是否可以领取,
            'message' => '请工作人员点击领取',
            'p' => $_GET['p'],
            'acid'=>$acid,
        );


        //检查是否已领取
        $res = $db->query("select * from play_ditui WHERE uid=? OR device_id=?", array($uid, $mid))->current();
        $user_data = $db->query("select * from play_user WHERE  uid=?", array($uid))->current();
        if ($res) {
            $data['get_gift'] = 1;
            $data['message'] = '已领取';
        } else {
            $s_time = time() - (3600 * 24);
            if ($user_data->dateline > $s_time) {
                $data['get_gift'] = 0;
                $data['can_get'] = 1;
            } else {
                $data['message'] = '只有新用户才能领取哦';

            }

        }
        return $data;


    }

    //确认领取接口
    public function getgiftAction()
    {

        $acid = (int)$this->getQuery('acid', 0);//活动id
        $param = $this->getWapUid(true);

        $uid = $param['uid'];
        $mid = $param['mid'];

        $data = array(
            'get_gift' => 0,//是否已领取
            'can_get' => 0,//是否可以领取,
            'message' => '请工作人员点击领取',
            'p' => $_GET['p'],
        );

        $db = $this->_getAdapter();
        //检查是否已领取
        $res = $db->query("select * from play_ditui WHERE uid=? OR device_id=?", array($uid, $mid))->current();
        $user_data = $db->query("select * from play_user WHERE  uid=?", array($uid))->current();
        if ($res) {
            $data['get_gift'] = 1;
            $data['message'] = '已领取';
        } else {
            $s_time = time() - (3600 * 24);
            if ($user_data->dateline > $s_time) {
                $data['get_gift'] = 0;
                $data['can_get'] = 1;
            } else {
                $data['message'] = '只有新用户才能领取哦';

            }

        }

        if($data['get_gift']==0 and $data['can_get']==1){
            $s=$db->query("INSERT INTO play_ditui (uid,dateline,device_id,gift_name,get_gift,get_gift_time,acid) VALUES (?,?,?,?,?,?,?)",array(
                $uid,time(),$mid,'小玩定制汤匙一份',1,time(),$acid
            ))->count();

            if($s){
                return $this->jsonResponsePage(array('message'=>'领取成功!'));
            }else{
                return $this->jsonResponsePage(array('message'=>'领取失败!'));
            }
        }else{
            return $this->jsonResponsePage(array('message'=>$data['message']));
        }
    }
}
