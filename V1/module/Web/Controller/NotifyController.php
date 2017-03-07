<?php

namespace Web\Controller;

use library\Service\User\Account;
use Deyi\Alipay\Alipay;
use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderExcercisePay;
use Deyi\OrderAction\OrderPay;
use Deyi\OrderAction\OrderBack;
use Deyi\OrderAction\OrderExcerciseBack;
use Deyi\OrderAction\UseCode;
use Deyi\Unionpay\Unionpay;
use Deyi\WeiXinFun;
use Deyi\ZybPay\ZybPay;
use Deyi\WeiSdkPay\WeiPay;
use library\Service\System\Logger;
use Zend\Mvc\Controller\AbstractActionController;

class NotifyController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use UseCode;
    use OrderBack;

    //支付宝回调
    public function aliAction()
    {

        // 接口验证
        $alipay = new Alipay();
        if (!$alipay->verifyNotify()) {
            //验证失败
            echo "fail";
            exit();
        }

        if ($_POST['trade_status'] !== 'TRADE_SUCCESS') {
            if ($_POST['trade_status'] !== 'TRADE_FINISHED') {
                //Logger::writeLog("支付宝支付失败 或其它". print_r($_POST, true) ."\n");
            }
            echo "success";
            exit;

        }

        //金额
        $total_fee = $_POST['total_fee'];
        //支付宝交易号
        $trade_no = $_POST['trade_no'];
        //账号
        $account = $_POST['buyer_email'];
        //商户订单号  out_trade_no
        $out_trade_no = preg_replace('/\D/s', '', $_POST['out_trade_no']);

        if ($_POST['body'] == 'recharge') {
            $chargeStatus = $this->recharge($out_trade_no, $trade_no, $total_fee, $account);
            if (!$chargeStatus) {
                Logger::WriteErrorLog("支付宝充值回调失败 ". print_r($_POST, true) ."\n");
            }
            exit("success");
        }

        //订单详情
        $data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $out_trade_no));

        if (!$data or $total_fee != $data->real_pay) {
            Logger::WriteErrorLog("支付宝支付金额异常". print_r($_POST, true) ."\n");
        }

        if ($data->pay_status >= 2) {
            Logger::writeLog("支付宝支付功 订单是已支付". print_r($_POST, true) ."\n");
        } else {
            if ($data->order_type == 3) {
                // 支付成功
                $order_pay = new OrderExcercisePay();
                $back_status = $order_pay->paySuccess($data, $trade_no, 'alipay', $_POST['buyer_email']);
            } else {
                // 支付成功
                $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $out_trade_no));
                $order_pay = new OrderPay();
                $back_status = $order_pay->paySuccess($data, $order_info_game, $trade_no, 'alipay', $_POST['buyer_email']);
            }

            if (!$back_status) {
                Logger::WriteErrorLog("支付宝支付成功 服务端回调失败". print_r($_POST, true) ."\n");
            }

        }

        exit("success");
    }

    //银联回调
    public function unionAction()
    {

        $union = new Unionpay();
        $result = $this->params()->fromPost();
        $r = $union->verify($result);

        if (!$r) {
            exit;
        }

        if ($result['respCode'] != '00' || $result['respMsg'] != 'Success!') {
            Logger::writeLog("银联支付失败". print_r($result, true) ."\n");
            exit;
        }

        //金额
        $total_fee = $result['settleAmt'] / 100;
        //银行流水号
        $trade_no = $result['queryId'];
        //用户帐号
        $account = ''; //银联为空
        //商户订单号  out_trade_no
        $out_trade_no = preg_replace('/\D/s', '', $result['orderId']);

        //判断是否充值回调
        if ($_POST['reqReserved'] == 'recharge') {
            $chargeStatus = $this->recharge($out_trade_no, $trade_no, $total_fee, $account);
            if (!$chargeStatus) {
                Logger::WriteErrorLog("银联充值回调失败 ". print_r($result, true) ."\n");
            }
            exit;
        }

        //订单详情
        $data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $out_trade_no));

        // 判断支付金额是否正常
        if (!$data || $total_fee != $data->real_pay) {
            Logger::WriteErrorLog("银联支付金额异常". print_r($result, true) ."\n");
        }

        if ($data->pay_status < 2) {
            // 支付成功
            if ($data->order_type == 3) {
                $order_pay = new OrderExcercisePay();
                $back_status = $order_pay->paySuccess($data, $trade_no, 'union',$account);
            } else {
                $order_pay = new OrderPay();
                $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $out_trade_no));
                $back_status = $order_pay->paySuccess($data, $order_info_game, $trade_no, 'union', $account);
            }

            if (!$back_status) {
                Logger::WriteErrorLog("银联支付成功 服务端回调失败". print_r($result, true) ."\n");
            }

        } else {
            Logger::WriteErrorLog("银联支付成功 订单是已支付". print_r($result, true) ."\n");
        }

        exit;

    }

    //微信回调
    public function weixinAction()
    {

        $xml = file_get_contents("php://input");
        $returnWeiData = array(
            'return_code' => 'SUCCESS',//SUCCESS/FAIL
            'return_msg' => 'OK'
        );

        if (!$xml) {
            exit;
        }

        $weiXin = new WeiXinFun($this->_getConfig()['wanfantian_weixin']);

        $xmlObj = $weiXin::xmlToObject($xml);
        $sign = $weiXin->getPaySignature($xmlObj);
        if ($sign != $xmlObj->sign) {//验证签名
            exit;
        }

        //判断结果
        $xmlArray = (array)$xmlObj;

        if ($xmlArray['return_code'] !== 'SUCCESS' || $xmlArray['result_code'] !== 'SUCCESS') {
            Logger::writeLog("微信网页支付失败". print_r($xmlObj, true) ."\n");
            echo WeiXinFun::ToXml($returnWeiData);
            exit;
        }

        //商户订单号  out_trade_no
        $out_trade_no = preg_replace('/\D/s', '', $xmlArray['out_trade_no']);
        //微信交易号
        $trade_no = $xmlArray['transaction_id'];
        //金额
        $total_fee = $xmlArray['total_fee'] / 100;
        //账号
        $account = $xmlArray['openid'];

        //判断是否充值回调
        if ($xmlArray['attach'] === 'recharge') {
            $chargeStatus= $this->recharge($out_trade_no, $trade_no, $total_fee, $account);
            if (!$chargeStatus) {
                Logger::WriteErrorLog("微信网页充值回调失败 ". print_r($xmlObj, true) ."\n");
            }
            echo WeiXinFun::ToXml($returnWeiData);
            exit;
        }

        //订单详情
        $data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $out_trade_no));

        if (!$data || $total_fee != $data->real_pay) {
            Logger::WriteErrorLog("微信网页支付失败 支付金额不一致". print_r($xmlObj, true) ."\n");
        }

        if ($data->pay_status < 2) {
            if ($data->order_type == 3) {
                $order_pay = new OrderExcercisePay();
                $back_status = $order_pay->paySuccess($data, $trade_no, 'new_jsapi', $account);
            } else {
                $order_pay = new OrderPay();
                $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $out_trade_no));
                $back_status = $order_pay->paySuccess($data, $order_info_game, $trade_no, 'new_jsapi', $account);
            }

            if (!$back_status) {
                Logger::WriteErrorLog("微信网页支付成功 服务端回调失败". print_r($xmlObj, true) ."\n");
            }
        } else {
            Logger::WriteErrorLog("微信网页支付成功 订单是已支付". print_r($xmlObj, true) ."\n");
        }

        echo WeiXinFun::ToXml($returnWeiData);
        exit;
    }

    //微信APP回调
    public function wftweixinsdkAction()
    {

        $xml = file_get_contents("php://input");
        $returnWeiData = array(
            'return_code' => 'SUCCESS',//SUCCESS/FAIL
            'return_msg' => 'OK'
        );

        if (!$xml) {
            exit;
        }

        $weiPay = new weiPay();
        $result = $weiPay->checkSign($xml);

        if (!$result) {
            exit;
        }

        if ($result['return_code'] !== 'SUCCESS' || $result['result_code'] !== 'SUCCESS') {
            Logger::writeLog("微信APP支付失败". print_r($result, true) ."\n");
            echo $weiPay->util->arrayToXml($returnWeiData);
            exit;
        }

        //金额
        $total_fee = $result['total_fee'] / 100;
        //用户帐号
        $account = $result['openid'];
        //银行流水号
        $trade_no = $result['transaction_id'];
        //商户订单号  order_sn
        $out_trade_no = preg_replace('/\D/s', '', $result['out_trade_no']);

        //判断是否充值回调
        if ($result['attach'] === 'recharge') {
            $chargeStatus= $this->recharge($out_trade_no, $trade_no, $total_fee, $account);
            if (!$chargeStatus) {
                Logger::WriteErrorLog("微信APP充值回调失败 ". print_r($result, true) ."\n");
            }
            echo $weiPay->util->arrayToXml($returnWeiData);
            exit;
        }

        //订单详情
        $data = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $out_trade_no));
        if (!$data || $total_fee != $data->real_pay) {
            Logger::WriteErrorLog("微信APP支付 支付金额不一致". print_r($result, true) ."\n");
        }

        if ($data->pay_status < 2) {
            if($data->order_type==3){
                $order_pay = new OrderExcercisePay();
                $back_status = $order_pay->paySuccess($data, $trade_no, 'weixin', $account);
            } else {
                $order_info_game = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $out_trade_no));
                $order_pay = new OrderPay();
                $back_status = $order_pay->paySuccess($data, $order_info_game, $trade_no, 'weixin', $account);
            }

            if (!$back_status) {
                Logger::WriteErrorLog("微信APP支付成功 服务端回调失败". print_r($result, true) ."\n");
            }

        } else {
            Logger::WriteErrorLog("微信APP支付成功 订单是已支付". print_r($result, true) ."\n");
        }

        echo $weiPay->util->arrayToXml($returnWeiData);
        exit;
    }

    //获取充值回调
    public function recharge($log_id, $trade_no, $total_fee, $user_account)
    {
        $log_data = $this->_getPlayAccountLogTable()->get(array('id' => $log_id));

        if (!$log_data || $total_fee != $log_data->flow_money) {
            Logger::WriteErrorLog("充值回调 金额异常 充值id:". $log_id ."\n");
        }

        if ($log_data->status != 0) {
            Logger::WriteErrorLog("充值回调 状态异常 已充值 充值id:". $log_id. "充值流水号". $trade_no. "  旧的流水号". $log_data->trade_no ."\n");
        }

        $Account = new Account();
        $status = $Account->successful($log_data->uid, $log_id, $trade_no, $user_account);
        return $status;
    }


    //###### 智游宝 ######

    /**
     *  //智游宝  检票完成：
     * 核销通知 回调url
     */

    public function zybFinishTicketAction()
    {

        $status = $this->getQuery('status');
        $order_no = $this->getQuery('order_no');
        $sub_order_no = $this->getQuery('sub_order_no');
        $checkNum = $this->getQuery('checkNum');
        $returnNum = $this->getQuery('returnNum');
        $checkTime = $this->getQuery('checkTime');
        $total = $this->getQuery('total');
        $sign = $this->getQuery('sign');

        $Zyb = new ZybPay();
        $privateSign = $Zyb->checkSign($order_no, 'checkTicket');

        $res = '';
        if ($privateSign == $sign && $checkNum > 0 && $status == 'check' && strtotime($checkTime) > time() - 24 * 3600 && $total >= $returnNum + $checkNum) {

            $code_id = substr($sub_order_no, 0, -7);
            $order_sn = (int)substr($order_no, (strpos($order_no, 'WFT') + 3));
            $Adapter = $this->_getAdapter();
            $Adapter->query("update play_zyb_info set status = 5 WHERE order_sn = ? AND code_id = ?", array($order_sn, $code_id))->count();

            $gameOrderInfo = $this->_getPlayOrderInfoGameTable()->get(array('order_sn' => $order_sn));

            if (!$gameOrderInfo) {
                exit;
            }

            $use_store = $this->_getPlayCodeUsedTable()->get(array('good_info_id' => $gameOrderInfo->game_info_id));

            if (!$use_store) {
                exit;
            }

            $this->UseCode($use_store->organizer_id, 2, $sub_order_no);

            $res = 'success';

        }

        //收到智游宝的回调 输出success
        echo $res;
        exit;

    }

    /**
     * 退票通知 回调url
     * 提交退票  系统会以审核完成时，回调下
     *
     */

    public function zybBackTicketAction()
    {

        $order_no = $this->getQuery('orderCode');

        $subOrderCode = $this->getQuery('subOrderCode');
        $auditStatus = $this->getQuery('auditStatus'); //failure/失败:success/成功
        //$retreatBatchNo =$this->getQuery('retreatBatchNo');
        //$returnNum = $this->getQuery('returnNum');
        $sign = $this->getQuery('sign');

        $Zyb = new ZybPay();
        $privateSign = $Zyb->checkSign($order_no, 'backTicket');

        if ($privateSign == $sign && $auditStatus == 'success') {

            $order_sn = (int)substr($order_no, (strpos($order_no, 'WFT') + 3));
            $code_id = substr($subOrderCode, 0, -7);
            $Adapter = $this->_getAdapter();
            $Adapter->query("update play_zyb_info set status = 4, back_time = ? WHERE order_sn = ? AND code_id = ?", array(time(), $order_sn, $code_id))->count();

            //todo 调用 确定退款

            echo 'success';
        }
        exit;
    }

    /**
     * 支付宝退款回调
     */
    public function aliPwdRefundAction()
    {

        $alipay = new Alipay();
        $result = $this->params()->fromPost();

        //验证签名
        if (!$alipay->verifySign()) {

            Logger::WriteErrorLog("支付宝确认退款验证签名失败\n");
            echo "fail";
            exit();
        }

        if (!$result['notify_type'] || $result['notify_type'] != 'batch_refund_notify') {
            Logger::WriteErrorLog("支付宝确认退款异常\n");
            echo "fail";
            exit();
        }

        $detail_data = explode('^', $result['result_details']);
        $batch_no = explode('WFT', $result['batch_no']);

        if (!$batch_no[1] || !$batch_no[2] || !$detail_data[0]) {
            Logger::WriteErrorLog("支付宝确认退款异常\n". print_r($result, true). "\n");
            echo "success";
            exit();
        }

        //查询这个退款订单是否存在
        $Adapter = $this->_getAdapter();
        $return_data = $Adapter->query("SELECT * FROM play_alipay_refund_log WHERE trade_no = ? AND order_sn = ? AND code_id = ?", array($detail_data[0], $batch_no[1], $batch_no[2]))->current();

        if (!$return_data) {
            Logger::WriteErrorLog("支付宝确认退款异常\n". print_r($result, true). "\n");
            echo "success";
            exit();
        }

        if ($return_data->back_money != $detail_data[1]) {
            Logger::WriteErrorLog("支付宝确认退款  退款金额异常\n". print_r($result, true). "\n");
            echo "success";
            exit();

        }

        if ($result['success_num'] != 1) {

            Logger::WriteErrorLog("支付宝确认退款  失败". print_r($result, true). "\n");
            if ($return_data->order_type == 2) {
                $this->aliLog(array(
                    'type' => 'fail',
                    'trade_no' => $detail_data[0],
                    'order_sn' => $batch_no[1],
                    'id' => $batch_no[2],
                    'mes' => $detail_data[2],
                ));
            } elseif ($return_data->order_type == 3) {
                $ActivityBack = new OrderExcerciseBack();
                $ActivityBack->aliLog(array(
                    'type' => 'fail',
                    'trade_no' => $detail_data[0],
                    'order_sn' => $batch_no[1],
                    'id' => $batch_no[2],
                    'mes' => $detail_data[2],
                ));
            }

            echo "success";
            exit();
        }

        //设置为退款成功
        if ($return_data->order_type == 2) {
            $this->aliLog(array(
                'type' => 'success',
                'trade_no' => $detail_data[0],
                'order_sn' => $batch_no[1],
                'id' => $batch_no[2],
            ));
        } elseif ($return_data->order_type == 3) {
            $ActivityBack = new OrderExcerciseBack();
            $ActivityBack->aliLog(array(
                'type' => 'success',
                'trade_no' => $detail_data[0],
                'order_sn' => $batch_no[1],
                'id' => $batch_no[2],
            ));
        } else {
            Logger::WriteErrorLog("支付宝确认退款  类型不正确". "\n");
        }

        echo "success";
        exit();

    }

    /**
     * 银联确定退款回调
     */
    public function unionRefundAction()
    {

        $union = new Unionpay();
        $result = $this->params()->fromPost();
        $r = $union->verify($result);
        if (!$r) {
            exit;
        }

        if ($result['respCode'] != '00' || $result['respMsg'] != 'Success!') {
            Logger::writeLog("银联确定退款失败". print_r($result, true) ."\n");
        }

        exit;

    }

}
