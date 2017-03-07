<?php

namespace Deyi\Alipay;

use Deyi\getServerConfig;
use Deyi\Request;
use library\Service\ServiceManager;

require_once("lib/alipay_notify.class.php");

/* *
 * 配置文件
 * 版本：3.3
 * 日期：2012-07-19
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 * 该代码仅供学习和研究支付宝接口使用，只是提供一个参考。

 * 提示：如何获取安全校验码和合作身份者id
 * 1.用您的签约支付宝账号登录支付宝网站(www.alipay.com)
 * 2.点击“商家服务”(https://b.alipay.com/order/myorder.htm)
 * 3.点击“查询合作者身份(pid)”、“查询安全校验码(key)”

 * 安全校验码查看时，输入支付密码后，页面呈灰色的现象，怎么办？
 * 解决方法：
 * 1、检查浏览器配置，不让浏览器做弹框屏蔽设置
 * 2、更换浏览器或电脑，重新登录查询。
 */

class Alipay
{

    //↓↓↓↓↓↓↓↓↓↓请在这里配置您的基本信息↓↓↓↓↓↓↓↓↓↓↓↓↓↓↓

    private $partner;  //合作身份者id，以2088开头的16位纯数字
    private $private_key_path = 'key/rsa_private_key.pem';//商户的私钥（后缀是.pen）文件相对路径
    private $ali_public_key_path = 'key/alipay_public_key.pem'; //支付宝公钥（后缀是.pen）文件相对路径

    //↑↑↑↑↑↑↑↑↑↑请在这里配置您的基本信息↑↑↑↑↑↑↑↑↑↑↑↑↑↑↑
    private $service = 'mobile.securitypay.pay';  //默认
    private $seller_id; //收款账号
    private $charset = 'utf-8';  //字符编码格式 目前支持 gbk 或 utf-8
    //private $notify_url = 'http://wft.deyi.com/web/notify/ali';  //回调地址
    private $notify_url;  //回调地址
    private $sign_type = 'RSA'; //签名方式 不需修改
    private $cacert = 'cacert.pem';  //ca证书路径地址，用于curl中ssl校验    //请保证cacert.pem文件在当前文件夹目录中
    private $transport = 'http'; //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http

    public function __construct()
    {
        $this->cacert = __DIR__ . '/' .$this->cacert;
        $this->private_key_path = __DIR__ . '/' . $this->private_key_path;
        $this->ali_public_key_path = __DIR__ . '/' . $this->ali_public_key_path;
        
        $this->partner = getServerConfig::get('alipay')['partner'];
        $this->seller_id = getServerConfig::get('alipay')['seller_id'];
        $this->notify_url = getServerConfig::get('alipay')['notify_url'];
        $this->MD5_key = getServerConfig::get('alipay')['MD5_key'];

    }

    public function getAlipayConfig()
    {

        $alipay_config['partner'] = $this->partner;

        $alipay_config['private_key_path'] = $this->private_key_path;

        $alipay_config['ali_public_key_path'] = $this->ali_public_key_path;

        $alipay_config['sign_type'] = $this->sign_type;

        $alipay_config['input_charset'] = $this->charset;

        $alipay_config['cacert'] = $this->cacert;

        $alipay_config['transport'] = $this->transport;
        return $alipay_config;
    }


    /**
     * 生成支付宝支预请求信息
     *
     * @param $order_sn     服务器生成的唯一订单号
     * @param $subject          订单标题
     * @param $total_fee        付款金额
     * @param $body             商品详情
     * @param $id               商品id
     * @param $count            购买数量
     * @param $name             购买者姓名
     * @param $phone            购买者手机号
     * @return array
     */
    public function alipay($order_sn, $subject, $total_fee, $body)
    {
        $ali = array(
            'service' => $this->service,
            'partner' => $this->partner,
            '_input_charset' => $this->charset,
            'notify_url' => urlencode($this->notify_url),//回调地址
            'out_trade_no' => $order_sn,//商户网站唯一订单号
            'subject' => $subject,//商品名称
            'payment_type' => 1,//支付类型
            'seller_id' => $this->seller_id,//支付宝账号
            'total_fee' => $total_fee,//总金额
            'body' => $body,//商品详情
            'it_b_pay' => bcdiv(ServiceManager::getConfig('TRADE_CLOSED'), 60).'m', //关闭订单时间 1m -h - 15d

        );
        $ali = $this->argSort($ali);
        $str = '';
        foreach ($ali as $key => $val) {
            if ($str == '') {
                $str = $key . '=' . '"' . $val . '"';
            } else {
                $str = $str . '&' . $key . '=' . '"' . $val . '"';
            }
        }
        //计算签名
        $sign = urlencode($this->sign($str));


        $url = $str . '&sign=' . '"' . $sign . '"' . '&sign_type=' . '"' . $this->sign_type . '"';//传给支付宝接口的数据
        return $url;
    }

    /**
     * 支付宝 无密退款接口
     * @param $transaction_id
     * @param $back_money
     * @param $refund_batch_no
     * @param $desc
     * @return array
     */
    public function aliPwdRefund($transaction_id, $back_money, $refund_batch_no, $desc = '协商退款') {

        //服务器异步通知页面路径
        $notify_url =   'http://wan.wanfantian.com/web/notify/aliPwdRefund';

        $parameter = array(
            "service" => "refund_fastpay_by_platform_nopwd",
            "partner" =>  $this->partner,
            "notify_url"	=> $notify_url,
            "batch_no"	=> $refund_batch_no,
            "refund_date"	=> date('Y-m-d H:i:s'),
            "batch_num"	=> 1,
            "detail_data"	=> $transaction_id . '^' . $back_money . '^' . $desc,
            "_input_charset"	=> $this->charset,
        );

        //对待签名参数数组排序
        $para_sort = $this->argSort($parameter);

        //生成签名结果
        $mySign = $this->buildRequestMysign($para_sort);

        $para_sort['sign'] = $mySign;
        $para_sort['sign_type'] = 'MD5';

        $query_url = 'https://mapi.alipay.com/gateway.do?';
        if (trim($this->charset) != '') {
            $query_url = $query_url."_input_charset=". $this->charset;
        }
        $curl = curl_init($query_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO, $this->cacert);//证书地址
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl,CURLOPT_POST,true); // post传输数据
        curl_setopt($curl,CURLOPT_POSTFIELDS, $para_sort);// post传输数据
        $responseText = curl_exec($curl);

        if($responseText){
            curl_close($curl);
            $result = $this->xmlToArray($responseText);

            if ($result['is_success'] === 'T') {
                return array('status' => '1', 'message' => '成功');
            }

            return array('status' => '0', 'message' => $result['error']);

        } else {
            $error = 'Curl error: ' . curl_error($curl). "\n". 'Curl error: ' . curl_errno($curl);
            curl_close($curl);
            return array('status' => 0, 'message' => 'curl errorCode:'. $error);
        }
    }

    //验证MD5 签名
    public function verifySign()
    {
        if (empty($_POST) || !$_POST['sign']) {//判断POST来的数组是否为空
            return false;
        } else {

            $return_sign = $_POST['sign'];
            $sign_type = $_POST['sign_type'];

            //生成签名结果
            $para_filter = array();
            while (list ($key, $val) = each ($_POST)) {
                if ($key == "sign" || $key == "sign_type" || $val == "") {
                    continue;
                } else {
                    $para_filter[$key] = $_POST[$key];
                }
            }

            switch ($sign_type) {
                case "MD5" :
                    $para_sort = $this->argSort($para_filter);
                    $mySign = $this->buildRequestMysign($para_sort);
                    $result = ($return_sign == $mySign) ? true : false;
                    break;
                default :
                    $result = false;
            }

            return $result;

        }
    }

    /**
     * 支付宝订单查询接口
     *
     * @param $order_sn
     * @return mixed
     */
    public function getOrderInfo($order_sn)
    {
        $out_trade_no = 'WFT'. $order_sn;
        $parameter = array(
            "service" => "single_trade_query",
            "partner" => $this->partner,
            "out_trade_no"	=> $out_trade_no,
            "_input_charset"	=> $this->charset,
        );

        //对待签名参数数组排序
        $para_sort = $this->argSort($parameter);

        //生成签名结果
        $mySign = $this->buildRequestMysign($para_sort);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mySign;
        $para_sort['sign_type'] = 'MD5';

        $query_url = 'https://mapi.alipay.com/gateway.do?';

        if (trim($this->charset) != '') {
            $query_url = $query_url."_input_charset=". $this->charset;
        }
        $curl = curl_init($query_url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
        curl_setopt($curl, CURLOPT_CAINFO, $this->cacert);//证书地址
        curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
        curl_setopt($curl,CURLOPT_POST,true); // post传输数据
        curl_setopt($curl,CURLOPT_POSTFIELDS, $para_sort);// post传输数据
        $responseText = curl_exec($curl);

        if($responseText){
            curl_close($curl);
            $result = $this->xmlToArray($responseText);
            if ($result['response']['trade']['trade_status'] === 'TRADE_SUCCESS') {
                return array('status' => '1', 'message' => $result['response']);
            }
            return array('status' => '0', 'message' => $result['response']['trade']['trade_status']);

        } else {
            $error = 'Curl error: ' . curl_error($curl). "\n". 'Curl error: ' . curl_errno($curl);
            curl_close($curl);
            return array('status' => 0, 'message' => 'curl errorCode:'. $error);
        }

    }

    /**
     * 支付宝对账单
     * @return mixed
     */
    public function getBalanceList() {

        //页号
        $page_no = 1;
        //必填，必须是正整数

        //账务查询开始时间
        $gmt_start_time = date("Y-m-d 00:00:00", time() - 24* 3600);
        //格式为：yyyy-MM-dd HH:mm:ss

        //账务查询结束时间
        $gmt_end_time = date("Y-m-d 00:00:00", time());
        //格式为：yyyy-MM-dd HH:mm:ss


        $parameter = array(
            "service" => "account.page.query",
            "partner" => $this->partner,
            "page_no"	=> $page_no,
            "gmt_start_time"	=> $gmt_start_time,
            "gmt_end_time"	=> $gmt_end_time,
            "_input_charset"	=> $this->charset
        );

        //对待签名参数数组排序
        $para_sort = $this->argSort($parameter);

        //生成签名结果
        $mySign = $this->buildRequestMysign($para_sort);

        //签名结果与签名方式加入请求提交参数组中
        $para_sort['sign'] = $mySign;
        $para_sort['sign_type'] = 'MD5';
        $query_url = 'https://mapi.alipay.com/gateway.do?';

        if (trim($this->charset) != '') {
            $query_url = $query_url."_input_charset=". $this->charset;
        }

        $res = Request::post($query_url, $para_sort, 30);

        $result = $this->xmlToArray($res);

        return $result;

    }

    /**
     * 排序
     * @param $para
     * @return mixed
     */
    function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }

    //淘宝RSA签名
    function sign($data)
    {
        //读取私钥文件
        $priKey = file_get_contents($this->private_key_path);//私钥文件路径
        //转换为openssl密钥，必须是没有经过pkcs8转换的私钥
        $res = openssl_get_privatekey($priKey);
        //调用openssl内置签名方法，生成签名$sign
        openssl_sign($data, $sign, $res);
        //释放资源
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }


    /**
     * @return \验证结果
     */
    public function verifyNotify()
    {
        $alipayNotify = new \AlipayNotify($this->getAlipayConfig());
        return $alipayNotify->verifyNotify();
    }


    private function buildRequestMysign($para_sort) {

        $arg = '';
        while (list($key, $val) = each($para_sort)) {
            $arg .= $key . '=' . $val . '&';
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return  md5($arg. $this->MD5_key);

    }

    /**
     * xml to array
     * @param $xml
     * @return mixed
     */
    private function xmlToArray($xml)
    {
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }


}


?>