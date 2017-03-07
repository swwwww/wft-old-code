<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Apiuser\Controller;

use Deyi\Account\Account;
use Deyi\Alipay\Alipay;
use Deyi\BaseController;
use Deyi\GetCacheData\NoticeCache;
use Deyi\Unionpay\Unionpay;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\WriteLog;
use library\Fun\Common;
use library\Fun\M;
use library\Service\Order\Order;
use library\Service\System\Logger;
use library\Service\User\Member;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Mcrypt;

class AccountController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    //个人账户
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $page = (int)$this->getParams('page', 1);
        $pagenum = (int)$this->getParams('pagenum', 10);
        if (!$uid) {
            return $this->jsonResponseError('用户id不存在');
        }

        $service_member = new Member();
        $data_member    = $service_member->getMemberData($uid);
        $data['is_vip'] = $data_member['member_level'] > 0 ? 1 : 0;

        $account = new Account();

        $data['money'] = sprintf("%.2f", $account->getUserMoney($uid));  //保留两位小数

        //流水记录
        $data['flows'] = array();

        $offset = ($page - 1) * $pagenum;
        $flows = $this->_getPlayAccountLogTable()->fetchLimit($offset, $pagenum, array(), array('uid' => $uid, 'status' => 1), array('id' => 'desc'));

        foreach ($flows as $v) {
            if ($v->action_type == 2) {
                if ($v->action_type_id == 1) {
                    $data_param_order_info = array(
                        'order_sn' => $v->object_id,
                    );
                    $data_order_info= M::getPlayOrderInfoTable()->getOrderInfo($data_param_order_info);



                    if (empty($data_order_info)) {
                        continue;
                    } else {
                        if ($data_order_info['order_type'] == 2) {
                            $v->action_type_id = 1;
                        } else {
                            $v->action_type_id = 2;
                        }
                    }
                    $v->description = preg_replace('/购买/', '【消费】', $v->description, 1);
                } elseif ($v->action_type_id == 2) {
                    $v->action_type_id = 5;
                    $v->description    = "退款至原账户";
                } elseif ($v->action_type_id == 3) {
                    $v->action_type_id = 5;
                    $v->description    = "账户提现";
                }
            } elseif ($v->action_type == 1) {
                if ($v->action_type_id == 1) {
                    $v->action_type_id = 4;
                    $v->description    = preg_replace('/退款/', '【退款】', $v->description, 1);
                } elseif ($v->action_type_id == 5) {
                    $v->action_type_id = 3;
                    $v->description    = preg_replace('/购买/', '【返现】', $v->description, 1);
                    $v->description    = preg_replace('/获得返利/', '', $v->description, 1);
                } elseif (in_array($v->action_type_id, array(2, 3, 12, 17))) {
                    $v->action_type_id = 0;
                    $v->description    = "账户充值";
                } elseif (in_array($v->action_type_id, array(4, 6, 7, 8, 9, 10, 11))) {
                    $v->action_type_id = 3;
                    $v->description    = "奖励返现";
                }
            }

            $data['flows'][] = array(
                'id'            => $v->id,
                'action_type'   => $v->action_type,
                'flow_type'     => $v->action_type_id,
                'flow_money'    => (string)($v->action_type==1?$v->flow_money:$v->flow_money*-1), //流水金额
                'surplus_money' => $v->surplus_money,//余款
                'dateline'      => $v->dateline,
                'desc'          => $v->description,
            );
        }


        return $this->jsonResponse($data);
    }

    //验证密码
    public function verifypasswordAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $uid = (int)$this->getParams('uid', 0);
        $password = $this->getParams('password');  //初始用户不用传

        if (!$uid) {
            return $this->jsonResponseError("参数错误");
        }
        $account_data = $this->_getPlayAccountTable()->get(array('uid' => $uid));

        if ($account_data->password) {
            if ($account_data->password !== md5(md5($password) . $account_data->salt)) {
                return $this->jsonResponse(array('status' => 0, 'message' => '密码错误'));
            } else {
                return $this->jsonResponse(array('status' => 1, 'message' => '密码正确'));
            }
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '未设置密码'));
        }
    }

    //修改支付密码
    public function updatePasswordAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $new_password = $this->getParams('new_password');
        $uid = $this->getParams('uid');


        if (!$new_password) {
            return $this->jsonResponseError('参数错误');
        }

        $acc = new Account();
        $acc->initAccount($uid);

        $salt = $this->randomkeys(6);
        $status = (int)$this->_getPlayAccountTable()->update(array('password' => md5(md5($new_password) . $salt), 'salt' => $salt), array('uid' => $uid));
        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '更新成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '与原密码相同'));
        }
    }

    //验证短信
    public function verifycodeAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $phone = $this->getParams('phone');
        $code = $this->getParams('code');
        $uid = $this->getParams('uid');

        if (!$phone or !$code or !$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

//        if ($user_data->phone != $phone) {
//            return $this->jsonResponse(array('status' => 0, 'message' => '不是绑定的手机号'));
//        }
        //验证码
        if (!$this->check_auth_code($phone, $code)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
        } else {
            $this->use_auth_code($phone, $code);
            return $this->jsonResponse(array('status' => 1, 'message' => '更新成功'));
        }
    }

    public function randomkeys($length)
    {
        $returnStr = '';
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLOMNOPQRSTUVWXYZ';
        for ($i = 0; $i < $length; $i++) {
            $returnStr .= $pattern{mt_rand(0, 62)}; //生成php随机数
        }
        return $returnStr;
    }


    //账户充值
    public function rechargeAction() {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        $money = $this->getParams('money', 0.00);// 保留两位
        $pay_type = (int)$this->getParams('paytype', 1); //支付类型  1 alipay  2 union 3 微信充值 4 微信网页充值
        $from_uid = (int)$this->getParams('from_uid', 0); // 分享用户的uid
        $city     = $this->getCity();
        $money = (float)$money;
        if (!$uid or !$money) {
            return $this->jsonResponseError('参数错误');
        }

        $user_data = M::getPlayUserTable()->get(array('uid'=>$uid));

        if(!$user_data->phone){
            return $this->jsonResponseError('请先绑定手机号');
        }

        $service_account = new \library\Service\User\Account();

        if ($pay_type == 1) {
            $action_type_id = 2;
        } elseif ($pay_type == 2) {
            $action_type_id = 3;
        } elseif ($pay_type == 3) {
            $action_type_id = 12;
        } elseif ($pay_type == 4) {
            $action_type_id = 25;
        } else {
            return $this->jsonResponseError('参数错误');
        }

        $data_money_service = M::getAdapter()->query("SELECT * FROM play_member_money_service WHERE money_service_status > 0 AND money_service_now_price <= ? ORDER BY money_service_now_price DESC LIMIT 1", array($money))->current();

        if (empty($data_money_service)) {
            $data_money_service_id = 0;
        } else {
            $data_money_service_id = $data_money_service->money_service_id;
        }

        $log_id = $service_account->recharge($uid, $money, 1, '用户充值', $action_type_id, 0, true, 0, $city, 0, $from_uid, $data_money_service_id);

        if ($log_id) {

            //测试服务器
            if (Common::isUp()) {
                $pay_order_sn = 'WFTREC' . $log_id;
                $order_name = '玩翻天-充值';
            } else {
                $pay_order_sn = 'TESTWFTREC' . $log_id;
                $order_name = 'TEST_玩翻天-充值';
            }

            $tag = "recharge";

            if ($pay_type == 1) {
                $alipay = new Alipay();
                $params = $alipay->alipay($pay_order_sn, $order_name, $money, $tag);
                return $this->jsonResponse(['status' => 1, 'params' => $params, 'order_sn' => $log_id]);
            } elseif ($pay_type == 2) {
                $union = new Unionpay();
                $params = $union->unionpay($pay_order_sn, $money, $tag);
                if (!isset($params['tn'])) {
                    if (!$params) {
                        return $this->jsonResponse(['status' => 0, 'message' => '请求失败，请稍候再试！']);
                    }
                    return $this->jsonResponse(['status' => 0, 'message' => $params['respMsg']]);
                }
                return $this->jsonResponse(['status' => 1, 'params' => ['tn' => $params['tn']], 'order_sn' => $log_id]);
            } elseif ($pay_type == 3) {

                $weiPay = new weiPay();
                $params = $weiPay->weiPay($pay_order_sn, $money, $order_name, $tag);

                if (!$params) {
                    return $this->jsonResponse(['status' => 0, 'message' => '请求失败，请稍候再试！']);
                }
                return $this->jsonResponse(['status' => 1, 'params' => $params, 'order_sn' => $log_id]);
            } elseif ($pay_type == 4) {
                $weiXinWap = new WeiXinPayFun($this->getwxConfig());
                $open_id = $this->getParams('open_id');
                $respOb = $weiXinWap->weixinPay($open_id, $order_name, $pay_order_sn, $money, $tag);
                if (!$respOb->prepay_id) {
                    Logger::writeLog('微信wap支付测试：'. print_r($respOb, true). "\r\n");
                    return $this->jsonResponse(['status' => 0, 'message' => '生成预支付订单失败']);
                }
                $timer = time();
                $payData = array(
                    'appId' => $this->getwxConfig()['appid'],
                    'timeStamp' => "$timer",
                    'nonceStr' => $weiXinWap::getNonceStr(),//随机字符串
                    'package' => 'prepay_id=' . $respOb->prepay_id,
                    'signType' => "MD5",
                );
                $payData['paySign'] = $weiXinWap->getPaySignature($payData);
                return $this->jsonResponse(array('pay_data'=>$payData,'order_sn' => $log_id,'status'=>1,));
            } else {
                return $this->jsonResponse(['status' => 0, 'message' => '支付类型错误']);
            }


        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '充值失败'));
        }
    }

    public function lotteryWinAction () {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid       = $this->getParams('uid');
        $record_id = $this->getParams('record_id');
        $city      = $this->getCity();
        if (empty($uid) || empty($record_id)) {
            return $this->jsonResponseError('参数错误');
        }

        $pdo  = M::getAdapter();
        $conn = $pdo->getDriver()->getConnection();
        $conn->beginTransaction();
        $data_lottery_record = $pdo->query(' SELECT * FROM ps_lottery_user_record WHERE user_id = ? AND id = ? LIMIT 1 ', array($uid, $record_id))->current();

        if (empty($data_lottery_record)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '中奖信息错误'));
        }

        if ($data_lottery_record->money > 1) {
            $this->errorLog('用户' . $uid . '在lottery_id为' . $data_lottery_record->lottery_id . '的活动中中奖金额超过范围，范围为' . 1 . '，实际为' . $data_lottery_record->money);
            return $this->jsonResponse(array('status' => 0, 'message' => '中奖信息无效'));
        }

        $data_lottery_win_count = $pdo->query(' SELECT sum(money) as win_count FROM ps_lottery_user_record WHERE user_id = ? AND lottery_id = ?', array($uid, $data_lottery_record->lottery_id))->current()->win_count;

        if ($data_lottery_win_count > 50) {
            $this->errorLog('用户' . $uid . '在lottery_id为' . $data_lottery_record->lottery_id . '的活动中中奖总额超过范围，范围为' . 50 . '，实际为' . $data_lottery_win_count);
            return $this->jsonResponse(array('status' => 0, 'message' => '中奖金额过多'));
        }

        $data_result_update_lottery_record = $pdo->query(' UPDATE ps_lottery_user_record SET status = 1 WHERE user_id = ? AND id = ? ', array($uid, $record_id))->count();

        if (!$data_result_update_lottery_record) {
            $conn->rollback();
            return $this->jsonResponse(array('status' => 0, 'message' => '充值失败请稍候重试'));
        }

        $conn->commit();

        $service_account = new Account();
        $log_id = $service_account->recharge($uid, $data_lottery_record->money, 0, '二周年刮奖活动奖励', 26, 0, false, 0, $city, 0, 0);

        if ($log_id > 0) {
            return $this->jsonResponse(array('status' => 1, 'message' => '充值成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '充值失败'));
        }
    }

    private function check_auth_code($phone, $code)
    {
        $data = $this->_getPlayAuthCodeTable()->fetchAll(array('phone' => $phone, 'code' => $code, 'status' => 1), array('id' => 'desc'), 1)->toArray();
        // if (!empty($data) and (time() - $data[0]['time']) < 300) {
        if (!empty($data)) {//todo 临时
            return true;
        }
        return false;
    }

    private function use_auth_code($phone, $code)
    {
        return $this->_getPlayAuthCodeTable()->update(array('status' => 0), array('phone' => $phone, 'code' => $code, 'status' => 1));
    }


}
