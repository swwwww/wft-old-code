<?php

namespace TianShi\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\OrderAction\UseCode;
use Deyi\Integral\Integral;
use Deyi\Coupon\Coupon;
use library\Service\System\Cache\RedCache;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class
IndexController extends AbstractActionController
{
    use JsonResponse;
    use UseCode;
    use BaseController;

    const token = 654321;


    public function __construct()
    {

//        //退出登录
//        if ($_GET['p'] and !$this->getWapUid()) {
//            unset($_GET['p']);
//            setcookie('tmp_p', '', time() - 10, '/');
//            $_COOKIE['tmp_p'] = false;
//        } else {
//            setcookie('tmp_p', $_GET['p'], time() + 3600 * 24 * 30, '/');
//            $_COOKIE['tmp_p'] = $_GET['p'];
//        }
//
//        //携带cookie请求
//        if ($_COOKIE['tmp_p']) {
//            $_GET['p'] = $_COOKIE['tmp_p'];
//            if (!$this->getWapUid()) {
//                unset($_GET['p'],$_COOKIE['tmp_p']);
//                setcookie('tmp_p', '', time() - 10, '/');
//
//            }
//        }

    }

    //查询页
    public function indexAction()
    {
        //存储cookie
        if (isset($_GET['p']) and $this->getWapUid()) {
            setcookie('tmp_p', $_GET['p'], time() + 3600 * 24 * 30, '/');
            $_COOKIE['tmp_p'] = $_GET['p'];
        } else {
            unset($_GET['p']);
            setcookie('tmp_p', '', time() - 10, '/');
            $_COOKIE['tmp_p'] = false;
        }
    }

    //结果页
    public function resultAction()
    {
        $_GET['p'] = $_COOKIE['tmp_p'];
        //检验js-token
        $html_token = $_GET['token'];
        if ($html_token != self::token) {
            $this->returnValue("参数错误", "0", "");
        }
        $test_uid = $this->getQuery("test_uid");
        if ($test_uid) {
            $uid = $test_uid;
        } else {
            $uid = $this->getWapUid();
        }
        if (!$uid) {
            echo "请在玩翻天APP中打开";
            exit;
        }
        $user = $this->_getPlayUserTable()->get(array('uid' => $uid));

        //获奖信息列表
        $sql = "SELECT a.*,l.status,l.number,l.log_id FROM play_award_log l LEFT JOIN play_award a ON a.award_id = l.award_id WHERE l.uid = $uid AND a.award_id >= '41' ORDER BY a.award_id DESC";
        $data = iterator_to_array($this->query($sql));
        //奖状（选取最好奖项）
        $sql2 = "SELECT a.*,l.status,l.number FROM play_award_log l LEFT JOIN play_award a ON a.award_id = l.award_id WHERE l.uid = $uid AND a.award_id >= '41' ORDER BY a.award_id ASC limit 0,1";
        $award = iterator_to_array($this->query($sql2))[0];

       /* if ($award['award_id'] == 12) {
            $award['title'] .= "+《阿阿熊》期刊系列封面宝宝（一期）";
        }
        if ($award['award_id'] == 2) {
            $award['title'] .= "+玩翻天50积分";
        }
        if ($award['award_id'] == 4) {
            $award['title'] .= "+玩翻天现金券2元";
        }*/
        if ($award['award_id'] == 41) {
            $award['title'] .= "＋奇乐儿仙林店2年至尊vip畅玩卡";
        }
        if ($award['award_id'] == 42) {
            $award['title'] .= "＋价值1498元的kimmy kids儿童摄影套系";
        }
        if ($award['award_id'] == 43) {
            $award['title'] .= "+价值520元的ABCswim国际亲子游泳体验套餐1份";
        }
        if ($award['award_id'] == 44) {
            $award['title'] .= "＋价值368元的kimmy kids儿童摄影体验套系";
        }
        if ($award['award_id'] == 45) {
            $award['title'] .= "＋价值150元的南京海底世界门票1张";
        }
        if ($award['award_id'] == 46) {
            $award['title'] .= "＋价值200元的金洋宝贝城单次票1张";
        }
        if ($award['award_id'] == 47) {
            $award['title'] .= "＋价值80元的贝哥可妹手工礼盒1份＋价值30元爬爬步步手工糖果1份";
        }
        if ($award['award_id'] == 48) {
            $award['title'] .= "＋价值15元卡通尼乐园体验券1张＋价值10元维利康代金券1张";
        }
        if ($award['award_id'] == 49) {
            $award['title'] .= "＋价值60元艾米1895电影街环亚店观影券1张＋玩翻天20元无门槛现金券＋巧虎精美礼包1份＋格瓦拉@电影10元红包";
        }
        if ($award['award_id'] == 50) {
            $award['title'] .= "＋价值60元艾米1895电影街环亚店观影券1张＋玩翻天20元无门槛现金券";
        }

        //键值重组
        foreach ($data as $value) {
            $result[$value['award_id']] = $value;
        }

        return [
            'data' => $result,
            'award' => $award,
            'user' => $user
        ];
    }

    //查询参赛结果
    public function queryAction()
    {

        $_GET['p'] = $_COOKIE['tmp_p'];
        //检验js-token
        $html_token = $this->getPost('token');
        if ($html_token != self::token) {
            $this->returnValue("参数错误", "0", "");
        }
        $uid = $this->getWapUid();
        $phone = $this->getPost('phone');//手机号
        $user_phone = $this->_getPlayUserTable()->get(array('phone' => $phone));
        $user = $this->_getPlayUserTable()->get(array('uid' => $uid));
        //注册登录状态
        if (!$user_phone) {//用户未注册
            $this->returnValue("请先注册", "0", "");
        }

        if (!$uid) {//未登录
            $this->returnValue("请先使用参赛手机号登录", "0", "");
        }
        //查询别人的参赛结果
        if ($user['phone'] != $phone) {
            $this->returnValue("请查询自己的参赛结果", "0", "");
        }

        if ($uid == '125966') {
            $award = [3, 4];
        } else {
            $award = $this->api($this->encryptParam($phone));
        }
        $this->insertLog($award, $uid);
        $this->returnValue("查询成功", "1", $phone);
    }


    public function useAction()
    {
        $_GET['p'] = $_COOKIE['tmp_p'];
        date_default_timezone_set("Asia/Shanghai");
        //检验js-token
        $html_token = $this->getPost('token');
        if ($html_token != self::token) {
            $this->returnValue("参数错误", "0", "");
        }
        $type = $this->getPost('type');
        $award_id = $this->getPost('awardid');
        $detail = $this->_getAwardTable()->get(array('award_id' => $award_id));
        if (!$award_id) {
            $this->returnValue("参数有误", "0", "");
        }
        $uid = $this->getWapUid();
        switch ($type) {
            case 1://邮寄
                $name = $this->getPost('name');
                $phone = $this->getPost('phone');
                $address = $this->getPost('address');
                $result = $this->_getAwardLogTable()->update(array('status' => 1, 'address' => "$name-$phone-$address", 'addtime' => date("Y-m-d H:i:s")), array('uid' => $uid, 'award_id' => 1));
                break;
            case 2://积分和购物券
                if ($detail['title'] == '玩翻天50积分') {
                    $result = $this->_getAwardLogTable()->update(array('status' => 1, 'addtime' => date("Y-m-d H:i:s")), array('uid' => $uid, 'award_id' => $award_id));

                    if ($result) {
                        $log = $this->_getAwardLogTable()->get(array('uid' => $uid, 'award_id' => $award_id));
                        $int = new Integral();
                        $int->addIntegral($uid, 50, 1, 24, $log['log_id'], 'WH', 0, "萌宝大赛");//积分
                    }

                } else {
                    $result = $this->_getAwardLogTable()->update(array('status' => 1, 'addtime' => date("Y-m-d H:i:s")), array('uid' => $uid, 'award_id' => $award_id));
                    if ($result) {
                        //购物券
                        $log = $this->_getAwardLogTable()->get(array('uid' => $uid, 'award_id' => $award_id));
                        $coupon = new Coupon();
                        $coupon->addCashcoupon($uid, 74, $log['log_id'], 4, 0, '萌宝大赛');
                    }
                }
                break;
            case 3://商家点击
                $code = $this->getPost("code");
                if ($code != 1234) {
                    $this->returnValue("验证码错误", "0", "");
                }
                $result = $this->_getAwardLogTable()->update(array('status' => 1, 'addtime' => date("Y-m-d H:i:s")), array('uid' => $uid, 'award_id' => $award_id));
                if (!$result) {
                    $this->returnValue("奖品不存在或已使用，请联系客服", "0", "");
                }
                break;
            default:
                //武商网购物券密码
                $result = $this->_getAwardLogTable()->update(array('status' => 1, 'addtime' => date("Y-m-d H:i:s")), array('uid' => $uid, 'award_id' => $award_id));
                if ($result) {
                    $data = $this->getCode($this->getPost('number'));//兑换码
                    $this->returnValue("领取成功", "1", $data);
                }

                break;
        }
        if ($result) {
            $this->returnValue("使用成功", "1", "");
        } else {
            $this->returnValue("使用失败或已经领取，请刷新再试", "0", "");
        }
    }

    //邮寄
    public function mailAction()
    {
        $_GET['p'] = $_COOKIE['tmp_p'];
        //检验js-token
        $html_token = $this->getPost('token');
        if ($html_token != self::token) {
            $this->returnValue("参数错误", "0", "");
        }
        $uid = $this->getWapUid();
        $data = $this->_getAwardLogTable()->get(array('uid' => $uid, 'award_id' => 1));
        $this->returnValue("查看成功", "1", $data);
    }

    //查询结果插入数据
    public function insertLog($award, $uid)
    {
        foreach ($award as $value) {
            $data = $this->_getAwardLogTable()->get(array('uid' => $uid, 'award_id' => $value));
            $detail = $this->_getAwardTable()->get(array('award_id' => $value));
            if (!$data and $detail["num"] > 0) {
                date_default_timezone_set("Asia/Shanghai");
                $this->_getAwardTable()->update(array('num' => new Expression('num-1')), array('award_id' => $value));//奖品数量减1
                $this->_getAwardLogTable()->insert(array('uid' => $uid, 'award_id' => $value, 'addtime' => date("Y-m-d H:i:s")));
            }
        }
    }


    //加密参数
    private function encryptParam($str, $key = 'M!F6rAz&')
    {
        $blockSize = mcrypt_get_block_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $pad = $blockSize - (strlen($str) % $blockSize);
        $str = $str . str_repeat(chr($pad), $pad);
        $encrypt = mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
        return bin2hex(base64_encode($encrypt));
    }

    //json返回
    public function returnValue($message, $code, $returnObject)
    {
        print_r(json_encode(array("m" => $message, "c" => $code, "o" => $returnObject)));
        die();
    }

    public function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    public function api($phone)
    {
        $data = file_get_contents("http://weixin.deyi.com/appvote/award?m=dearbaby&site=2&phone=" . $phone);
        $result = json_decode($data, true);

        if ($result['status'] == 0) {
            $this->returnValue("验证失败", "0", "");
        }
        if ($result['status'] == 2) {
            $this->returnValue("用户未报名", "0", "");
        }

        if (in_array(2, $result['msg'])) {
            array_push($result['msg'], 22);
        }
        if (in_array(4, $result['msg'])) {
            array_push($result['msg'], 24);
        }
        if (in_array(12, $result['msg'])) {
            array_push($result['msg'], 32);
        }
        if (in_array(41, $result['msg'])) {
            array_push($result['msg'], 51);
        }
        if (in_array(42, $result['msg'])) {
            array_push($result['msg'], 52);
        }
        if (in_array(43, $result['msg'])) {
            array_push($result['msg'], 53);
        }
        if (in_array(44, $result['msg'])) {
            array_push($result['msg'], 54);
        }
        if (in_array(45, $result['msg'])) {
            array_push($result['msg'], 55);
        }
        if (in_array(46, $result['msg'])) {
            array_push($result['msg'], 56);
        }
        if (in_array(47, $result['msg'])) {
            array_push($result['msg'], 57,58);
        }
        if (in_array(48, $result['msg'])) {
            array_push($result['msg'], 59,60);
        }
        if (in_array(49, $result['msg'])) {
            array_push($result['msg'], 61,62,63,64);
        }
        if (in_array(50, $result['msg'])) {
            array_push($result['msg'], 65,66);
        }
        return $result['msg'];
    }

    public function getCode($type)
    {
        $_GET['p'] = $_COOKIE['tmp_p'];
        $uid = $this->getWapUid();
        if ($type == 20) {//满40减20
            $sql = "select * from play_wu_twenty where status = '0' limit 0,1";
            $data = iterator_to_array($this->query($sql))[0];
            $id = $data['id'];
            $this->query("update play_wu_twenty set status = '1',uid = $uid where id = $id");//改变领取状态
            return $data;
        } elseif ($type == 50) {//发2张50代金券
            $sql = "select * from play_gewara_code where status = '0' limit 0,1";
            $data = iterator_to_array($this->query($sql))[0];
            $id = $data['id'];
            $this->query("update play_gewara_code set status = '1',uid = $uid where id = $id");//改变领取状态
            return $data;
        } else {//满100减50
            $sql = "select * from play_wu_hundred where status = '0' limit 0,1";
            $data = iterator_to_array($this->query($sql))[0];
            $id = $data['id'];
            $this->query("update play_wu_hundred set status = '1',uid = $uid where id = $id");//改变领取状态
            return $data;
        }
    }

    public function lookAction()
    {
        //检验js-token
        $_GET['p'] = $_COOKIE['tmp_p'];
        $html_token = $this->getPost('token');
        if ($html_token != self::token) {
            $this->returnValue("参数错误", "0", "");
        }
        $number = $this->getPost('number');
        $uid = $this->getWapUid();
        if ($number == 20) {//满40减20
            $sql = "select * from play_wu_twenty where status = '1' AND uid = '$uid'";
            $data = iterator_to_array($this->query($sql))[0];
            $this->returnValue("查看成功", "1", $data);
        } elseif ($number == 50) {//发2张50代金券
            $sql = "select * from play_gewara_code where status = '1' AND uid = '$uid'";
            $data = iterator_to_array($this->query($sql))[0];
            $this->returnValue("查看成功", "1", $data);
        } else {//满100减50
            $sql = "select * from play_wu_hundred where status = '1' AND uid = '$uid'";
            $data = iterator_to_array($this->query($sql))[0];
            $this->returnValue("查看成功", "1", $data);
        }
    }

    public function demoAction(){
        echo (file_get_contents("http://weixin.deyi.com/appvote/award?m=wftbaby&site=2&phone=" . $this->encryptParam("18672792276")));die;
    }

}
