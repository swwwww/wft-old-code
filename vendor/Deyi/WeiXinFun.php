<?php

namespace Deyi;

use library\Service\System\Cache\RedCache;

class WeiXinFun
{
    protected $appid;
    protected $secret;
    protected $token;
    protected $accessToken;

    protected $wxConfig;


    public function __construct($config)
    {
        $this->wxConfig = $config;
        $this->appid = $config['appid'];
        $this->secret = $config['secret'];
        $this->token = $config['token'];
    }

    public function getappid()
    {
        return $this->appid;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 输出xml字符
     * @throws WxPayException
     **/
    public static function ToXml($data)
    {
        if (!is_array($data) || count($data) <= 0) {
            return false;
        }

        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * xml 转object
     * @param $data |xml
     * @return bool|\SimpleXMLElement
     */
    public static function xmlToObject($data)
    {
        if (!$data) {
            return false;
        } else {
            return simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
    }

    /**
     * 获取微信请求参数
     * @return null|SimpleXMLElement
     */
    public function getRequestObject()
    {
        $requestObject = null;
        //$requestData = isset($GLOBALS["HTTP_RAW_POST_DATA"]) ? $GLOBALS["HTTP_RAW_POST_DATA"] : null;
        $requestObject=file_get_contents("php://input");
        if (!empty($requestData)) {
            $requestObject = simplexml_load_string($requestData, 'SimpleXMLElement', LIBXML_NOCDATA);
        }
        return $requestObject;
    }

    /**
     * 接口验证
     *
     * @return bool
     */
    public function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = $this->token;
        $tmpArr = [$token, $timestamp, $nonce];
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode($tmpArr);
        $tmpStrSha1 = sha1($tmpStr);
        if ($tmpStrSha1 == $signature) {
            return true;
        } else {
            \Deyi\WriteLog::WriteLog('验证失败 RAW:' . $tmpStr . ' sha1:' . $tmpStrSha1);
            return false;
        }
    }

    /**设置菜单
     * @param $menuJson
     * @return mixed
     */
    public function setMenu($menuJson)
    {
//        $menuJson = '{
//    "button": [
//        {
//            "type": "view",
//            "name": "历史消息",
//            "url": "http://mp.weixin.qq.com/mp/getmasssendmsg?__biz=MjM5NTM3Mzc5OQ==#wechat_webview_type=1&wechat_redirect"
//        },
//
//        {
//            "name": "玩转APP",
//            "sub_button": [
//                {
//                    "type": "view",
//                    "name": "下载玩翻天APP",
//                    "url": "http://wft.deyi.com/app/index.php"
//                }
//
//            ]
//        },
//
//	{
//            "name": "联系我们",
//            "sub_button": [
//                {
//                    "type": "click",
//                    "name": "联系我们",
//                    "key": "info"
//                }
//            ]
//        }
//    ]
//}';
        $resp = Request::post('https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->getAccessToken(), $menuJson);
        return json_decode($resp, true);
    }

    /**获取access_token
     * @param $appid
     * @param $secret
     * @return mixed
     */
    public function getAccessToken($appid = '', $secret = '')
    {

        $appid = $appid ? $appid : $this->appid;
        $secret = $secret ? $secret : $this->secret;
        $aToken = RedCache::get("accessToken{$appid}");
        if ($aToken) {
            return $aToken;
        } else {
            $res = Request::get("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}");
            $token_data = json_decode($res);
            if ($token_data->access_token) {
                RedCache::set("accessToken{$appid}", $token_data->access_token, $token_data->expires_in - 5);
                return $token_data->access_token;
            } else {
                \Deyi\WriteLog::WriteLog('请求token失败,RAW:' . $res);
            }
        }
    }

    /**
     * 回复消息  text|news
     * @param $reqMsg
     * @param $respMsg
     * @return null|string
     */
    public function responseMsg($reqMsg, $respMsg)
    {
        $resultStr = null;
        if ($reqMsg) {
            $to = $reqMsg->FromUserName;
            $from = $reqMsg->ToUserName;
            $time = time();
            header('Content-Type:text/xml', true);
            if ($respMsg['type'] == 'text') {
                $msg['text'] = "<xml>
                                    <ToUserName><![CDATA[%s]]></ToUserName>
                                    <FromUserName><![CDATA[%s]]></FromUserName>
                                    <CreateTime>%s</CreateTime>
                                    <MsgType><![CDATA[%s]]></MsgType>
                                    <Content><![CDATA[%s]]></Content>
                                    <FuncFlag>0</FuncFlag>
                                </xml>";
                $resultStr = sprintf($msg['text'], $to, $from, $time, $respMsg['type'], htmlspecialchars_decode($respMsg['data']));
            }

            if ($respMsg['type'] == 'news') {
                $msg['newsitem'] = "<item>
                                        <Title><![CDATA[%s]]></Title>
                                        <Description><![CDATA[%s]]></Description>
                                        <PicUrl><![CDATA[%s]]></PicUrl>
                                        <Url><![CDATA[%s]]></Url>
                                    </item>";

                $msg['news'] = "<xml>
                                    <ToUserName><![CDATA[%s]]></ToUserName>
                                    <FromUserName><![CDATA[%s]]></FromUserName>
                                    <CreateTime>%s</CreateTime>
                                    <MsgType><![CDATA[news]]></MsgType>
                                    <ArticleCount>%s</ArticleCount>
                                    <Articles>%s</Articles>
                                </xml>";
                $news = '';
                $newsCount = count($respMsg['data']);
                if ($newsCount) {
                    foreach ($respMsg['data'] as $new) {
                        $new['description'] = isset($new['description']) ? $new['description'] : '';
                        if (preg_match('/^\/uploads/', $new['img'])) {
                            $img_url = 'http://wan.deyi.com'. $new['img'];
                        } else {
                            $img_url = $new['img'];
                        }
                        $news .= sprintf($msg['newsitem'], $new['title'], $new['description'], $img_url, $new['to_url']);
                    }
                }
                $resultStr = sprintf($msg['news'], $to, $from, $time, $newsCount, $news);
            }
            /* $msg['image'] = "<xml>
                                     <ToUserName><![CDATA[toUser]]></ToUserName>
                                     <FromUserName><![CDATA[fromUser]]></FromUserName>
                                     <CreateTime>12345678</CreateTime>
                                     <MsgType><![CDATA[image]]></MsgType>
                                     <Image>
                                     <MediaId><![CDATA[media_id]]></MediaId>
                                     </Image>
                                 </xml>";

             $msg['music'] = "<xml>
                                 <ToUserName><![CDATA[toUser]]></ToUserName>
                                 <FromUserName><![CDATA[fromUser]]></FromUserName>
                                 <CreateTime>12345678</CreateTime>
                                 <MsgType><![CDATA[music]]></MsgType>
                                 <Music>
                                 <Title><![CDATA[TITLE]]></Title>
                                 <Description><![CDATA[DESCRIPTION]]></Description>
                                 <MusicUrl><![CDATA[MUSIC_Url]]></MusicUrl>
                                 <HQMusicUrl><![CDATA[HQ_MUSIC_Url]]></HQMusicUrl>
                                 <ThumbMediaId><![CDATA[media_id]]></ThumbMediaId>
                                 </Music>
                             </xml>";

             $msg['video'] = "<xml>
                                 <ToUserName><![CDATA[toUser]]></ToUserName>
                                 <FromUserName><![CDATA[fromUser]]></FromUserName>
                                 <CreateTime>12345678</CreateTime>
                                 <MsgType><![CDATA[video]]></MsgType>
                                 <Video>
                                 <MediaId><![CDATA[media_id]]></MediaId>
                                 <Title><![CDATA[title]]></Title>
                                 <Description><![CDATA[description]]></Description>
                                 </Video>
                             </xml>";

             $msg['voice'] = "<xml>
                                 <ToUserName><![CDATA[toUser]]></ToUserName>
                                 <FromUserName><![CDATA[fromUser]]></FromUserName>
                                 <CreateTime>12345678</CreateTime>
                                 <MsgType><![CDATA[voice]]></MsgType>
                                 <Voice>
                                 <MediaId><![CDATA[media_id]]></MediaId>
                                 </Voice>
                             </xml>";*/
        }
        return $resultStr;
    }





    /********************* JSAPI ***********************/

    /**
     * 获取微信jsAPI ticket
     * @return bool|string
     */
    private function getJsapi_ticket()
    {
        $ticket = RedCache::get("weixin_ticket{$this->appid}");
        if (!$ticket) {
            $token = $this->getAccessToken();
            $res = Request::get("https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi");
            $ticket = json_decode($res)->ticket;
            RedCache::set("weixin_ticket{$this->appid}", $ticket, 7000);
            return $ticket;
        } else {
            return $ticket;
        }
    }

    /**
     * @param $data
     * @return string|sign
     */
    public function getPaySignature($data)
    {
        ksort($data);
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $string = trim($buff, "&");
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->wxConfig['PartnerKey'];
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;

    }

    /**
     *生成JSAPI 签名，返回所需的所有签名数据
     */
    public function getsignature()
    {
        $data = [
            'noncestr' => '3pGPCxqmotfp',  //随机字符串
            'jsapi_ticket' => $this->getJsapi_ticket(),
            'timestamp' => time(),
            'url' => 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
        ];
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= "$k=$v&";
        }
        $str = substr($str, 0, strlen($str) - 1);
        $signature = sha1($str);

        $data['signature'] = $signature;
        $data['appid'] = $this->appid;
        return $data;


//    wx.config({
//    debug: true, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
//    appId: '', // 必填，公众号的唯一标识
//    timestamp: , // 必填，生成签名的时间戳
//    nonceStr: '', // 必填，生成签名的随机串
//    signature: '',// 必填，签名，见附录1
//    jsApiList: [] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
//});

    }

    /********************* JSAPI NED ***********************/





    /**********************  WeiXinWapAPI *****************************/

    /**获取授权页面url
     * @param bool|false $callbackUrl
     * @param string $scope |snsapi_base|snsapi_userinfo
     * @param string $state
     * @return string
     */
    public function getAuthorUrl($callbackUrl = false, $scope = 'snsapi_userinfo', $state = '123')
    {
        if (!$callbackUrl) {
            $callbackUrl = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        $callbackUrl = urlencode($callbackUrl);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$callbackUrl}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
    }

    /**
     * 获取取用户 access_token
     * @param $code
     * @return mixed
     */
    public function getUserAccessToken($code)
    {
        $access_token = Request::get("https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->secret}&code={$code}&grant_type=authorization_code");
        return json_decode($access_token);
    }

    public function getUserInfo($user_access_token)
    {
        $userInfo = Request::get("https://api.weixin.qq.com/sns/userinfo?access_token={$user_access_token}&openid={$this->appid}&lang=zh_CN");
        return json_decode($userInfo);
    }

    /**
     * 是否通过微信访问
     * @return bool
     */
    public function isWeiXin()
    {
        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 是否通过玩翻天APP访问
     * @return bool
     */
    public function isWft()
    {
        if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'wft') === false) {
            return false;
        } else {
            return true;
        }
    }

    /**********************  WeiXinWapAPI END *****************************/





    /***************************玩翻天微信地推部分开始*****************************/

    /**
     * 生成带参数的永久二维码的ticket
     * @param int $sceneId 渠道参数，如1 2 3 ...
     * @return bool|string
     */
    public function getDituiApi_ticket($sceneId)
    {
        $ticket = RedCache::get("weixin_ditui_ticket{$sceneId}");//从缓存中取ticket
        if (!$ticket) {//如果缓存中没有则生成
            $access_token = $this->getAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_token";
            $qrcode = [
                'action_name' => 'QR_LIMIT_SCENE',
                'action_info' => [
                    'scene' => [
                        'scene_id' => $sceneId
                    ]
                ]
            ];
            $qrcode = json_encode($qrcode);
            $res = Request::post($url, $qrcode);

            $ticket = json_decode($res)->ticket;
            RedCache::set("weixin_ditui_ticket{$sceneId}", $ticket, 1);
        }
        return $ticket;
    }

    /**
     * https_post方法访问微信接口
     * @param $url
     * @param null $data
     * @return mixed
     */
    public function https_post($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 获取普通用户信息
     */
    public function getOdinaryUserInfo($openId)
    {
        $access_token = $this->getAccessToken();
        $userInfo = Request::get("https://api.weixin.qq.com/cgi-bin/user/info?access_token={$access_token}&openid={$openId}&lang=zh_CN");
        $res =json_decode($userInfo);
//        if(!$res->openid){
//            throw new \Exception("access_token error time:".date('Y-m-d H:i:s'));
//        }
        return $res;
    }

    /***************************玩翻天微信地推部分结束*****************************/

    /*************************发送微信模板消息  $data内容，$type模板类型********************/
    public function set_http_message($data){
        $token = $this->getAccessToken();
        $url ='https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$token;
        $res = $this->http_request($url,$data);
        return json_decode($res,true);
    }

    private function http_request($url,$data){
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
        if(!empty($data)){
            curl_setopt($curl,CURLOPT_POST,1);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }

        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    //data=array(接收方openid,购买者用户名，返利金额，分享者订单号)
    //type
    public function getTemplate($type,$data,$msg){
        //构造模板数据
        if($type==='buy'){//购买成功
            $url = "http://wan.wanfantian.com/web/wappay/allorder";
            $first = urlencode($msg);
            $remark = urlencode('欢迎再次购买！');
        }elseif($type==='back'){//退款通知
            $url = "http://wan.wanfantian.com/web/wappay/allorder";
            $first = urlencode($msg);
            $remark = urlencode('立即分享！');
        }elseif($type==='use'){//返利
            $url = "http://wan.wanfantian.com/web/wappay/account";
            $first = urlencode($msg);
            $remark = urlencode('点击查看玩翻天账户！');
        }
        return array(
            "touser"=>$data['open_id'],
            "template_id"=>'PPRg6et3UFQlCLJLbZf6SRwDFJUFZtoZ7wgzJ2SllBo',
            "url"=>$url,
            "data"=>array(
                "first"=> array(
                    "value"=>$first,
                    "color"=>"#743A3A"
                ),
                "keyword1"=>array(
                    "value"=>urlencode($data['order_sn']),
                    "color"=>"#FF0000"
                ),
                "keyword2"=>array(
                    "value"=>$data['cash'],
                    "color"=>"#C4C400"
                ),
                "remark"=>array(
                    "value"=>$remark,
                    "color"=>"#008000"
                ),
            )
        );
    }
}