<?php

namespace Deyi\WeiXinPay;


use Application\Module;
use Deyi\Request;
use Deyi\WeiXinFun;
use Deyi\WriteLog;
use library\Service\ServiceManager;
use library\Service\System\Logger;

class WeiXinPayFun extends WeiXinFun
{


    //生成预支付订单
    public function weixinPay($openid, $couponName, $order_id, $real_pay, $tag = '123')
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $reqData = array(
            'appid' => $this->appid,
            'openid' => $openid,
            'mch_id' => $this->wxConfig['PartnerID'],
            'nonce_str' => WeiXinFun::getNonceStr(),//随机
            'body' => $couponName, //必
            'detail' => '商品描述', //选
            'attach' => $tag,  //其他
            'out_trade_no' => $order_id, //订单号
            'total_fee' => bcmul($real_pay, 100), //单位分
            'spbill_create_ip' => $_SERVER['REMOTE_ADDR'],
            'time_start' => date('YmdHis', time()),  //交易起始时间
            'time_expire' => date('YmdHis', (time() + ServiceManager::getConfig('TRADE_CLOSED'))), //交易失效时间 15分钟
            'notify_url' => $this->wxConfig['notify_url'],//通知地址
            'trade_type' => 'JSAPI',//交易类型
            'device_info' => 'WEB',
            'fee_type' => 'CNY'
        );
//        Logger::writeLog("预支付之前:".print_r($reqData,true));
        $reqData['sign'] = $this->getPaySignature($reqData);
        $xml = WeiXinFun::ToXml($reqData);
//        Logger::writeLog("预支付之后:".$xml);
        $sponXml = WeiXinPayFun::postXmlfile_get_contents($xml, $url, false, 40);
        return WeiXinFun::xmlToObject($sponXml);
    }

    /**
     * 查询订单详情
     * @param $order_sn //商户订单号
     * @return bool|\SimpleXMLElement
     */
    public function getOrderInfo($order_sn)
    {

        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        $reqData = array(
            'appid' => $this->appid,
            'mch_id' => $this->wxConfig['PartnerID'],
            'out_trade_no' => $order_sn,
            'nonce_str' => WeiXinFun::getNonceStr(),//随机
        );
        $reqData['sign'] = $this->getPaySignature($reqData);
        $xml = WeiXinFun::ToXml($reqData);
        $result = Request::post($url,$xml);
        return $this->xmlToArray($result);

    }

    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml 需要post的xml数据
     * @param string $url url
     * @param bool $useCert 是否需要证书，默认不需要
     * @param int $second url执行超时时间，默认30s
     * @throws WxPayException
     */
    public static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);


        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //终止从服务端进行验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//严格校验 0/2 关闭/开启
        //curl_setopt($ch, CURLOPT_SSLVERSION, 3);  //指定版本 // 等待测试
        //设置header
        //curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
//            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
//            curl_setopt($ch, CURLOPT_SSLCERT, __DIR__ . '/cert/apiclient_cert.pem');
//            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
//            curl_setopt($ch, CURLOPT_SSLKEY, __DIR__ . '/cert/apiclient_key.pem');
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            if (!$data) {
                //todo log
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                Module::$serviceManager->get('Logger')->crit('weixin_pay_log 返回为空状态码为' . print_r($httpCode, true));
            }

            return $data;
        } else {
            $error = curl_errno($ch);
            //todo log
            Module::$serviceManager->get('Logger')->crit('weixin_pay_error_log' . $error . $data);
            curl_close($ch);
            return false;
        }
    }


    public static function postXmlfile_get_contents($xml, $url, $useCert = false, $second = 30){
        $opts = array('http' =>
            array(
                'method'  => 'POST',
               // 'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $xml,
                'timeout' => $second
            )
        );

        $context  = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }

    /**
     * 微信app退款
     * @param $transaction_id //transaction_id  out_refund_no 微信订单号 商户订单号 二选一
     * @param $out_refund_no //商户退款单号 商户系统内部的退款单号，商户系统内部唯一，同一退款单号多次请求只退一笔
     * @param $total_fee //总金额
     * @param $refund_fee //退款金额
     * @return mixed
     *  //$result['return_code'] $result['result_code'] SUCCESS 时 成功  $result['err_code_des']] 错误代码描述
     */
    public function wxRefund($transaction_id, $out_refund_no, $total_fee, $refund_fee) {

        //设置接口链接
        $url = "https://api.mch.weixin.qq.com/secapi/pay/refund";

        $params = array(
            'appid' => $this->appid,
            'mch_id' => $this->wxConfig['PartnerID'],
            'nonce_str' => WeiXinFun::getNonceStr(),//随机
            'op_user_id' => $this->wxConfig['PartnerID'],
            'out_refund_no' => $out_refund_no,
            'transaction_id' => $transaction_id,
            'total_fee' => (int)bcmul($total_fee, 100),
            'refund_fee' => (int)bcmul($refund_fee, 100),
            //'refund_account' => 'REFUND_SOURCE_RECHARGE_FUNDS'
        );

        $params["sign"] =  $this->getPaySignature($params);//签名
        $xml = WeiXinFun::ToXml($params);
        return $this->curl_post_ssl($url, $xml, 40);
    }

    /**
     * 微信退款 结果查询
     * @param $out_refund_no //商户退款单号
     * @return mixed
     */
    public function wxRefundInfo($out_refund_no)
    {

        $url = "https://api.mch.weixin.qq.com/pay/refundquery";

        $params = array(
            'appid' => $this->appid,
            'mch_id' => $this->wxConfig['PartnerID'],
            'nonce_str' => WeiXinFun::getNonceStr(),//随机
            'out_refund_no' => $out_refund_no,
        );

        $params["sign"] =  $this->getPaySignature($params);//签名

        $xml = WeiXinFun::ToXml($params);
        $sponXml = WeiXinPayFun::postXmlfile_get_contents($xml, $url, false, 40);
        return WeiXinFun::xmlToObject($sponXml);
    }



    private function curl_post_ssl($url, $vars, $second=30,$aHeader=array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

        //以下两种方式需选择一种

        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, __DIR__ . '/cert/apiclient_cert.pem');

        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, __DIR__ . '/cert/apiclient_key.pem');

        //第二种方式，两个文件合成一个.pem文件
        //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);

            $result = $this->xmlToArray($data);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                return array('status' => '1', 'message' => 'success');
            }

            if ($result['return_code'] === 'FAIL') {
                return array('status' => '0', 'message' => $result['return_msg']);
            }

            return array('status' => '0', 'message' => '失败'. $result['err_code']. $result['return_msg']);

            //return array('status' => '0', 'message' => $result['err_code_des']);
        }
        else {
            $error = 'Curl error: ' . curl_error($ch). "\n". 'Curl error: ' . curl_errno($ch);
            WriteLog::WriteLog($error, 1);
            curl_close($ch);
            return array('status' => 0, 'message' => 'curl errorCode:'. $error);
        }
    }

    private function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    /**
     * 对账单
     * @return mixed
     */
    public function getBalanceList()
    {
        $url = "https://api.mch.weixin.qq.com/pay/downloadbill";
        $curl_timeout = 30;
        $gmt_time = date("Ymd", time() - 24* 3600);

        $reqData = array(
            'appid' => $this->appid,
            'mch_id' => $this->wxConfig['PartnerID'],
            'nonce_str' => WeiXinFun::getNonceStr(),//随机
            'bill_date' => $gmt_time,
            'bill_type' => 'ALL'
        );

        $reqData["sign"] =  $this->getPaySignature($reqData);//签名
        $xml = WeiXinFun::ToXml($reqData);

        $res = Request::post($url, $xml, $curl_timeout);
        return $res;

    }

}