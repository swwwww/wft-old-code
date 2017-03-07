<?php
namespace Deyi\Unionpay;

use Deyi\getServerConfig;
use Deyi\Request;
use Deyi\WriteLog;
use library\Service\ServiceManager;

class Unionpay
{

    public $config;

    function __construct()
    {
        $this->config = [
//            'sign_cert_path' => __DIR__ . '/cert/dy.pfx', //老证书
            'sign_cert_path' => __DIR__ . '/cert/wft_web.pfx',  //新证书
            'sign_cert_pwd' => getServerConfig::get('unionpay')['sign_cert_pwd'],
            'merid' => getServerConfig::get('unionpay')['merid'],
            'verify_cert_path' => __DIR__ . '/cert/UPOP_VERIFY.cer',
            'encript_cert_path' => __DIR__ . '/cert/RSA2048_PROD_index_22.cer',
            'verify_cert_dir' => __DIR__ . '/cert',
            'front_trans_url' => 'https://gateway.95516.com/gateway/api/frontTransReq.do',
            'back_trans_url' => 'https://gateway.95516.com/gateway/api/backTransReq.do',
            'batch_trans_url' => 'https://gateway.95516.com/gateway/api/batchTrans.do',
            'single_query_url' => 'https://gateway.95516.com/gateway/api/queryTrans.do',
            'file_query_url' => 'https://filedownload.95516.com/',
            'card_request_url' => 'https://gateway.95516.com/gateway/api/cardTransReq.do',
            'app_requert_url' => 'https://gateway.95516.com/gateway/api/appTransReq.do',

            'front_notify_url' => getServerConfig::get('unionpay')['front_notify_url'],  //前台通知地址
            'back_notify_url' => getServerConfig::get('unionpay')['back_notify_url']    //后台通知地址


        ];
    }


    /**
     * @param $out_trade_no //订单号
     * @param $total_fee //金额
     * @param $reqReserved_json //回调时原样返回json  产品id 产品名称 数量 单价
     * @return array
     */
    public function unionpay($out_trade_no, $total_fee, $reqReserved_json)
    {
        $unionpay_config = $this->config;
        $params = array(
            'version' => '5.0.0',                        //版本号
            'encoding' => 'UTF-8',                        //编码方式
            'certId' => $this->getSignCertId(),                //证书ID
            'txnType' => '01',                                //交易类型
            'txnSubType' => '01',                            //交易子类
            'bizType' => '000201',                            //业务类型
            'frontUrl' => $unionpay_config['front_notify_url'],                //前台通知地址
            'backUrl' => $unionpay_config['back_notify_url'],                //后台通知地址
            'signMethod' => '01',        //签名方法
            'channelType' => '08',                    //渠道类型
            'accessType' => '0',                            //接入类型
            'merId' => $unionpay_config['merid'],                    //商户代码
            'orderId' => $out_trade_no,                    //商户订单号
            'txnTime' => date('YmdHis', time()),        //订单发送时间，格式为YYYYMMDDhhmmss，重新产生，不同于原消费
            'payTimeout' => date('YmdHis', (time() + ServiceManager::getConfig('TRADE_CLOSED'))), //交易失效时间
            'txnAmt' => $total_fee * 100,                                //交易金额 单位为分
            'currencyCode' => '156',                        //交易币种
            'reqReserved' => $reqReserved_json              //回调时原样返回
        );

        $this->union_sign($params);

        $front_uri = $unionpay_config['app_requert_url'];
        $string = $this->sendHttpRequest($params, $front_uri);
        return $this->coverStringToArray($string);
    }

    /**
     * 银联退款
     */
    public function unRefund($query_id, $refund_fee) {


        $unionpay_config = $this->config;
        $backUrl =   'http://wan.wanfantian.com/web/notify/unionRefund'; //后台通知地址

        $params = array(
            //以下信息非特殊情况不需要改动
            'version' => '5.0.0',              //版本号
            'encoding' => 'UTF-8',              //编码方式
            'certId' => $this->getSignCertId(), //证书ID
            'signMethod' => '01',              //签名方法
            'txnType' => '04',                  //交易类型
            'txnSubType' => '00',              //交易子类
            'bizType' => '000201',              //业务类型
            'accessType' => '0',              //接入类型
            'channelType' => '07',              //渠道类型
            'backUrl' => $backUrl, //后台通知地址201612311245444
            'orderId' => date('YmdHis').  mt_rand(1000000, 9999999),        //商户订单号，8-32位数字字母，不能含“-”或“_”，可以自行定制规则，重新产生，不同于原消费
            'merId' => $unionpay_config['merid'],            //商户代码，请改成自己的测试商户号
            'origQryId' => $query_id,  //原消费的queryId，可以从查询接口或者通知接口中获取
            'txnTime' => date('YmdHis'),        //订单发送时间，格式为YYYYMMDDhhmmss，重新产生，不同于原消费
            'txnAmt' => $refund_fee * 100,       //交易金额，退货总金额需要小于等于原消费
  		    //'reqReserved' =>'透传信息',            //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据
        );

        $this->union_sign($params);

        $front_url = $unionpay_config['back_trans_url'];
        $string = $this->sendHttpRequest($params, $front_url);
        $result =  $this->coverStringToArray($string);

        if ($result['respCode'] === '00') {
            return ['status' => '1', 'message' => '受理成功'];
        } else {
            return ['status' => '0', 'message' => $result['respMsg']];
        }

    }

    //查询订单信息
    public function getOrderInfo($order_sn)
    {

        $orderId = 'WFT'.$order_sn;
        $params = array(
            'version' => '5.0.0',
            'encoding' => 'UTF-8',
            'certId' => $this->getSignCertId(),
            'signMethod' => '01',
            'txnType' => '00',
            'txnSubType' => '00',
            'bizType' => '000000',
            'accessType' => '0',
            'merId' => $this->config['merid'],
            'orderId' => $orderId,
            'txnTime' => date('YmdHis')
        );
        $this->union_sign($params);
        $string = Request::post($this->config['single_query_url'], $params, 30);
        return $this->coverStringToArray($string);

    }

    /**
     * 获取银联对账单
     * @return array
     */
    public function getBalanceList()
    {
        $settleTime = date('md', time()-3600*24);
        $params = array(
            'version' => '5.0.0',
            'encoding' => 'UTF-8',
            'certId' => $this->getSignCertId(),
            'signMethod' => '01',
            'txnType' => '76',
            'txnSubType' => '01',
            'bizType' => '000000',
            'accessType' => '0',
            'merId' => $this->config['merid'],
            'txnTime' => date('YmdHis'),
            'fileType' => '00',
            'settleDate' => $settleTime
        );
        $this->union_sign($params);
        $string = Request::post($this->config['file_query_url'], $params, 30);
        return $this->coverStringToArray($string);
    }


    /**
     * 签名证书ID
     *
     * @return unknown
     */
    public function getSignCertId()
    {
        // 签名证书路径
        return $this->getCertId($this->config['sign_cert_path']);
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

    public function getCertId($cert_path)
    {

        $sign_cert_pwd = $this->config['sign_cert_pwd'];
        $pkcs12certdata = file_get_contents($cert_path);

        openssl_pkcs12_read($pkcs12certdata, $certs, $sign_cert_pwd);
        $x509data = $certs ['cert'];
        openssl_x509_read($x509data);
        $certdata = openssl_x509_parse($x509data);
        $cert_id = $certdata ['serialNumber'];
        return $cert_id;
    }

    public function union_sign(&$params)
    {
        $sign_cert_path = $this->config['sign_cert_path'];
        if (isset($params['transTempUrl'])) {
            unset($params['transTempUrl']);
        }
        // 转换成key=val&串
        $params_str = $this->coverParamsToString($params);

        $params_sha1x16 = sha1($params_str, FALSE);
        // 签名证书路径
        $private_key = $this->getPrivateKey($sign_cert_path);
        // 签名
        $sign_falg = openssl_sign($params_sha1x16, $signature, $private_key, OPENSSL_ALGO_SHA1);
        if ($sign_falg) {
            $signature_base64 = base64_encode($signature);
            $params ['signature'] = $signature_base64;
        } else {
            echo 'sign error';
//            $this->log_result('签名失败');
        }
    }

    public function coverParamsToString($params)
    {
        $sign_str = '';
        // 排序
        ksort($params);
        foreach ($params as $key => $val) {
            if ($key == 'signature') {
                continue;
            }
            $sign_str .= sprintf("%s=%s&", $key, $val);
            // $sign_str .= $key . '=' . $val . '&';
        }
        return substr($sign_str, 0, strlen($sign_str) - 1);
    }

    public function getPrivateKey($cert_path)
    {
        $sign_cert_pwd = $this->config['sign_cert_pwd'];
        $pkcs12 = file_get_contents($cert_path);
        openssl_pkcs12_read($pkcs12, $certs, $sign_cert_pwd);
        return $certs ['pkey'];
    }


    /**
     * 后台交易 HttpClient通信
     * @param unknown_type $params
     * @param unknown_type $url
     * @return mixed
     */
    public function sendHttpRequest($params, $url)
    {

        return Request::post($url,$params);


        //curl error code 35
//        $opts = $this->getRequestParamString($params);
//        $ch = curl_init();
//        curl_setopt($ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//不验证证书
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//不验证HOST
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//            'Content-type:application/x-www-form-urlencoded;charset=UTF-8'
//        ));
//        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts);
////        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
//
//        /**
//         * 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
//         */
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//
//        // 运行cURL，请求网页
//        $html = curl_exec($ch);
//        if (!$html) {
//            $error = 'Curl error: ' . curl_error($ch). "\n". 'Curl error: ' . curl_errno($ch);
//            WriteLog::WriteLog($error, 1);
//        }
//        curl_close($ch);
//        return $html;
    }

    /**
     * 组装报文
     *
     * @param unknown_type $params
     * @return string
     */
    public function getRequestParamString($params)
    {
        $params_str = '';
        foreach ($params as $key => $value) {
            $params_str .= ($key . '=' . (!isset ($value) ? '' : urlencode($value)) . '&');
        }
        return substr($params_str, 0, strlen($params_str) - 1);
    }


    /**
     * 验证回调消息
     * @param $params
     * @return int
     */
    public function verify($params)
    {
        // 公钥
        $public_key = $this->getPulbicKeyByCertId($params ['certId']);
        // 签名串
        $signature_str = $params ['signature'];
        unset ($params ['signature']);
        $params_str = $this->coverParamsToString($params);
        // $this->log_result( '报文去[signature] key=val&串>' . $params_str );
        $signature = base64_decode($signature_str);
        $params_sha1x16 = sha1($params_str, FALSE);
        // $this->log_result ( '摘要shax16>' . $params_sha1x16 );
        $isSuccess = openssl_verify($params_sha1x16, $signature, $public_key, OPENSSL_ALGO_SHA1);
        // $this->log_result ( $isSuccess ? '验签成功' : '验签失败' );
        return $isSuccess;
    }


    public function getPulbicKeyByCertId($certId)
    {
        //  $this->log_result( '报文返回的证书ID>' . $certId );
        // 证书目录
        $cert_dir = $this->config['verify_cert_dir'];
        // $this->log_result ( '验证签名证书目录 :>' . $cert_dir );
        $handle = opendir($cert_dir);
        if ($handle) {
            while ($file = readdir($handle)) {
                clearstatcache();
                $filePath = $cert_dir . '/' . $file;
                if (is_file($filePath)) {
                    if (pathinfo($file, PATHINFO_EXTENSION) == 'cer') {
                        if ($this->getCertIdByCerPath($filePath) == $certId) {
                            closedir($handle);
                            // $this->log_result ( '加载验签证书成功' );
                            return file_get_contents($filePath);
                        }
                    }
                }
            }
            // $this->log_result ( '没有找到证书ID为[' . $certId . ']的证书' );
        } else {
            // $this->log_result ( '证书目录 ' . $cert_dir . '不正确' );
        }
        closedir($handle);
        return null;
    }

    /**
     * 取证书ID(.cer)
     *
     * @param unknown_type $cert_path
     */
    private function getCertIdByCerPath($cert_path)
    {
        $x509data = file_get_contents($cert_path);
        openssl_x509_read($x509data);
        $certdata = openssl_x509_parse($x509data);
        $cert_id = $certdata ['serialNumber'];
        return $cert_id;
    }

}