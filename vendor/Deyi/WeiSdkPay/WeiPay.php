<?php

namespace Deyi\WeiSdkPay;

include_once  __DIR__ . '/func/CommonUtil.php';

use Deyi\getServerConfig;
use Deyi\Request;
use Deyi\WriteLog;
use library\Service\ServiceManager;

class WeiPay
{

    public $config;

    function __construct()
    {
        $this->config = [
            'appid' => getServerConfig::get('weixin_sdk')['appid'],
            'key' => getServerConfig::get('weixin_sdk')['key'],
            'partnerid' => getServerConfig::get('weixin_sdk')['partnerid'],
            'notify' => getServerConfig::get('weixin_sdk')['notify_url'],
            'cert_pem' => __DIR__ . '/cert/apiclient_cert.pem',
            'private_pem' => __DIR__ . '/cert/apiclient_key.pem',
        ];

        $this->util = new \CommonUtil($this->config);
    }

    /**
     * @param $out_trade_no //订单号
     * @param $total_fee //金额 分
     * @param $body  //商品名称 商品描述
     * @param $attach //自定义
     * @return array
     */
    public function weiPay($out_trade_no, $total_fee, $body, $attach = 'pay')
    {

        $params = array(
            'body' => $body,
            'out_trade_no' => $out_trade_no,
            'total_fee' => bcmul($total_fee, 100),
            'notify_url' => $this->config['notify'],
            'attach' => $attach,
        );

        //获取统一支付接口结果
        $result = $this->unifiedOrder($params);

        if (!isset($result['prepay_id']) || !$result['prepay_id']) {

            return false;
            /*return array(
                'msg' => '获取统一支付接口结果出错'
            );*/
        }

        //签名
        $prepay_id = $result['prepay_id'];

        //再次签名
        $signData = $this->reSign($prepay_id);
        $sign = $signData['sign'];
        $noncestr = $signData['noncestr'];

        $outPrams = array(
            'appid' => $this->config['appid'],
            'noncestr' => $noncestr,
            'package' => 'Sign=WXPay',
            'partnerid' => $this->config['partnerid'],
            'prepayid' => $prepay_id,
            'timestamp' => time(),
            'sign' => $sign,
        );

        return $outPrams;

        /*$timestamp = time();

        $str = 'appid="' . $this->config['appid'] . '"&noncestr="' . $noncestr . '"&package="Sign=WXPay"' . '&partnerid="'. $this->config['partnerid'];
        $str = $str. '"&prepayid="' . $prepay_id . '"&timestamp="'. $timestamp . '"&sign="' . $sign . '"';

        return $str;*/
    }


    /**
     * 获取统一支付接口结果
     * @param $params
     * @return array
     */
    private function unifiedOrder($params) {

        //设置接口链接
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //设置curl超时时间
        $curl_timeout = 30;

        $body = $params['body'];
        $out_trade_no = $params['out_trade_no'];
        $total_fee = $params['total_fee'];
        $notify_url = $params['notify_url'];
        $trade_type = 'APP';
        $attach = $params['attach'];

        $unifiedOrderParameter = array();

        $unifiedOrderParameter['body'] = $body;//商品描述
        $unifiedOrderParameter['out_trade_no'] = $out_trade_no;//商户订单号
        $unifiedOrderParameter['total_fee'] = $total_fee;//总金额
        $unifiedOrderParameter['notify_url'] = $notify_url;//通知地址
        $unifiedOrderParameter['trade_type'] = $trade_type;//交易类型
        $unifiedOrderParameter['attach'] = $attach;//自定义数据
        $unifiedOrderParameter['time_start'] = date('YmdHis', time());//交易起始时间
        $unifiedOrderParameter['time_expire'] = date('YmdHis', (time() + ServiceManager::getConfig('TRADE_CLOSED')));//自定义数据

        $xml = $this->createXml($unifiedOrderParameter);
        $response = $this->postXmlfile_get_contents($xml, $url, $curl_timeout);
        $unifiedOrderResult = $this->util->xmlToArray($response);

        return $unifiedOrderResult;

    }

    /**
     * 再次签名
     * @return array
     */
    private function reSign($prepay_id) {

        $nonceStr = $this->util->createNoncestr();
        $prePayParams = array();
        $prePayParams['package'] = 'Sign=WXPay';
        $prePayParams['appid'] = $this->config['appid'];
        $prePayParams['partnerid'] = $this->config['partnerid'];
        $prePayParams['prepayid'] =$prepay_id;
        $prePayParams['noncestr'] = $nonceStr;
        $prePayParams['timestamp'] = time();
        $sign = $this->util->getSign($prePayParams);
        return ['sign' => $sign, 'noncestr' => $nonceStr];
    }

    /**
     * 验证签名
     * @param $xml
     * @return bool|mixed
     */
    public function checkSign($xml)
    {
        $data = $this->util->xmlToArray($xml);
        $tempData = $data;
        unset($tempData['sign']);
        $sign = $this->util->getSign($tempData);//本地签名
        if ($data['sign'] == $sign) {
            return $data;
        }
        return FALSE;
    }


    /**
     * 	作用：设置标配的请求参数，生成签名，生成接口参数xml
     */
    public function createXml($unifiedOrderParameter)
    {
        $parameters = $unifiedOrderParameter;
        $parameters["appid"] =$this->config['appid'];//公众账号ID
        $parameters["mch_id"] =$this->config['partnerid'];//商户号
        $parameters["nonce_str"] = $this->util->createNoncestr();//随机字符串
        $parameters["sign"] =  $this->util->getSign($parameters);//签名
        return  $this->util->arrayToXml($parameters);
    }

    /**
     * 	作用：post请求xml
     */
    public function postXml($unifiedOrderParameter, $url, $curl_timeout)
    {
        $xml = $this->createXml($unifiedOrderParameter);
        $response = $this->util->postXmlCurl($xml, $url, $curl_timeout);
        return $response;
    }



    /**
     * 	作用：使用证书post请求xml
     */
    public function postXmlSSL()
    {
        $xml = $this->createXml();
        $this->response = $this->postXmlSSLCurl($xml,$this->url,$this->curl_timeout);
        return $this->response;
    }

    /**
     *  作用 替换微信postXml 里面的curl
     * @param $xml
     * @param $url
     * @param int $second
     * @return string
     */
    public static function postXmlfile_get_contents($xml, $url, $second = 30){
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $xml,
                'timeout' => $second
            )
        );

        $context  = stream_context_create($opts);
        return file_get_contents($url, false, $context);
    }


    public function coverStringToArray($str)
    {
        $result = array();

        if (!empty ($str)) {
            $temp = preg_split('/&/', $str);
            if (!empty ($temp)) {
                foreach ($temp as $key => $val) {
                    $arr = preg_split('/=/', $val, 2);
                    if (!empty ($arr)) {
                        $k = $arr ['0'];
                        $v = $arr ['1'];
                        $result [$k] = $v;
                    }
                }
            }
        }
        return $result;
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
        //设置curl超时时间
        $curl_timeout = 30;

        $params = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['partnerid'],
            'nonce_str' => $this->util->createNoncestr(),
            'op_user_id' => $this->config['partnerid'],
            'out_refund_no' => $out_refund_no,
            'transaction_id' => $transaction_id,
            'total_fee' => (int)bcmul($total_fee, 100),
            'refund_fee' => (int)bcmul($refund_fee, 100),
            //'refund_account' => 'REFUND_SOURCE_RECHARGE_FUNDS'
        );

        $params["sign"] =  $this->util->getSign($params);//签名

        $xml =  $this->util->arrayToXml($params);

        return $this->curl_post_ssl($url, $xml, $curl_timeout);
    }


    /**
     * 微信退款 结果查询
     * @param $transaction_id //微信订单号
     * @return mixed
     */
    public function wxRefundInfo($transaction_id)
    {

        $url = "https://api.mch.weixin.qq.com/pay/refundquery";
        $curl_timeout = 30;

        $params = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['partnerid'],
            'nonce_str' => $this->util->createNoncestr(),
            'transaction_id' => $transaction_id,
        );

        $params["sign"] = $this->util->getSign($params);//签名
        $xml = $this->util->arrayToXml($params);
        $response = $this->postXmlfile_get_contents($xml, $url, $curl_timeout);
        $result = $this->util->xmlToArray($response);
        return $result;

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
        curl_setopt($ch,CURLOPT_SSLCERT, $this->config['cert_pem']);
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, $this->config['private_pem']);

        //第二种方式，两个文件合成一个.pem文件
        //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            $result = $this->util->xmlToArray($data);

            if ($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS') {
                return array('status' => '1', 'message' => 'success');
            }

            return array('status' => '0', 'message' => $result['err_code']. $result['return_msg']);

        } else {
            $error = 'Curl error: ' . curl_error($ch). "\n". 'Curl error: ' . curl_errno($ch);
            WriteLog::WriteLog($error, 1);
            curl_close($ch);
            return array('status' => 0, 'message' => 'curl errorCode:'. $error);
        }
    }

    /**
     * 查询订单详情
     * @param $order_sn //商户订单号
     * @return bool|\SimpleXMLElement
     */
    public function getOrderInfo($order_sn)
    {
        $url = "https://api.mch.weixin.qq.com/pay/orderquery";
        $curl_timeout = 30;

        $out_trade_no = 'WFT'. $order_sn;

        $reqData = array(
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['partnerid'],
            'out_trade_no' => $out_trade_no,
            'nonce_str' => $this->util->createNoncestr(),//随机
        );

        $reqData["sign"] = $this->util->getSign($reqData);//签名

        $xml = $this->util->arrayToXml($reqData);

        $res = Request::post($url, $xml, $curl_timeout);
        $result = $this->util->xmlToArray($res);

        return $result;

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
            'appid' => $this->config['appid'],
            'mch_id' => $this->config['partnerid'],
            'nonce_str' => $this->util->createNoncestr(),//随机
            'bill_date' => $gmt_time,
            'bill_type' => 'ALL'
        );

        $reqData["sign"] = $this->util->getSign($reqData);//签名

        $xml = $this->util->arrayToXml($reqData);

        $res = Request::post($url, $xml, $curl_timeout);
        return $res;

    }

}
