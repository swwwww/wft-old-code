<?php

class PhoneMessage
{

    public static $error = '';

    //首先调用玩翻天,失败后调用得意
    public static function Send($phone, $content)
    {
        $status = false;


        //2016.10.20 紧急需求,临时屏蔽指定商品
        if (strstr($content, '武汉极地海洋世界五周年特邀家庭年卡 2大1小家庭年卡"已支付成功') !== false) {
            return "屏蔽类型\n";
        }


        /**************** 可调整渠道顺序 ***********/

        if (!$status) {
            $status = self::Send_Message_Chuang($phone, $content);
        }

        if (!$status) {
            if (strstr($content, '验证码') !== false) {//判断是否发送验证码
                $status = self::Send_Message_code($phone, $content);
                if (!$status) {
                    $status = self::Send_Message($phone, $content);
                }
            } else {
                $status = self::Send_Message($phone, $content);
            }
        }

        if (!$status) {
            $status = self::Send_Message_Deyi($phone, $content);
        }
        if (!$status) {
            $status = self::Send_Message_Deyi_2($phone, $content);
        }


        if ($status) {
            return "发送成功\n";
        } else {
            //错误记录
            file_put_contents(__DIR__ . '/../../log/' . date('Y-m-d') . '-error.log', "短信服务异常" . self::$error . "\n", FILE_APPEND);
            return "发送失败,错误信息:" . self::$error . "\n";
        }
    }


    /************************* 短信渠道开始 ***************************************/
    //玩翻天梦网  专门发验证码
    private static function Send_Message_code($phone, $content)
    {

        $iMobiCount = substr_count($phone, ',') + 1;

        $content = str_replace('“玩翻天”', '', $content);  //去掉玩翻天
        $content = urlencode($content);
        $urlTemplate2 = "http://120.196.116.126:8027/MWGate/wmgw.asmx/MongateCsSpSendSmsNew?userId=M10021&password=518962&pszMobis={$phone}&pszMsg={$content}&iMobiCount={$iMobiCount}&pszSubPort=*";
        $output = file_get_contents($urlTemplate2);
        if (!$output) {
            self::$error = '第三方接口无数据返回';
            return false;
        }
        $xmlDoc = new \DOMDocument();
        $xmlDoc->loadXML($output);
        $status_code = $xmlDoc->getElementsByTagName('string')->item(0)->textContent;
        $strlen = strlen($status_code);

        if ($strlen >= 10 and $strlen <= 25) {
            return true;
        } else {
            self::$error = $strlen;
            return false;
        }
    }


    //玩翻天梦网  其他短信
    private static function Send_Message($phone, $content)
    {

        $content = str_replace('“玩翻天”', '', $content);  //去掉玩翻天
        $content = urlencode($content);
        $urlTemplate2 = "http://61.145.229.29:7791/MWGate/wmgw.asmx/MongateCsSpSendSmsNew?userId=H10906&password=598602&pszMobis={$phone}&pszMsg={$content}&iMobiCount=1&pszSubPort=*";
        $output = file_get_contents($urlTemplate2);
        if (!$output) {
            self::$error = '第三方接口无数据返回';
            return false;
        }
        $xmlDoc = new \DOMDocument();
        $xmlDoc->loadXML($output);
        $status_code = $xmlDoc->getElementsByTagName('string')->item(0)->textContent;
        $strlen = strlen($status_code);

        if ($strlen >= 10 and $strlen <= 25) {
            return true;
        } else {
            self::$error = $strlen;
            return false;
        }
    }


    //得意梦网
    private static function Send_Message_Deyi($phone, $content)
    {

        $iMobiCount = substr_count($phone, ',') + 1;
        $content = urlencode($content);
        $urlTemplate2 = "http://61.145.229.29:9006/MWGate/wmgw.asmx/MongateCsSpSendSmsNew?userId=J02282&password=588620&pszMobis={$phone}&pszMsg={$content}&iMobiCount={$iMobiCount}&pszSubPort=*";
        $output = file_get_contents($urlTemplate2);
        if (!$output) {
            self::$error = '第三方接口无数据返回';
            return false;
        }
        $xmlDoc = new \DOMDocument();
        $xmlDoc->loadXML($output);
        $status_code = $xmlDoc->getElementsByTagName('string')->item(0)->textContent;
        $strlen = strlen($status_code);

        if ($strlen >= 10 and $strlen <= 25) {
            return true;
        } else {
            self::$error = $strlen;
            return false;
        }
    }

    //创蓝短信
    private static function Send_Message_Chuang($phone, $content)
    {
        $RemindMsg = array(
            '0' => '发送成功',
            '101' => '无此用户',
            '102' => '密码错',
            '103' => '提交过快',
            '104' => '系统忙',
            '105' => '敏感短信',
            '106' => '消息长度错',
            '107' => '错误的手机号码',
            '108' => '手机号码个数错',
            '109' => '无发送额度',
            '110' => '不在发送时间内',
            '111' => '超出该账户当月发送额度限制',
            '112' => '无此产品',
            '113' => 'extno格式错',
            '115' => '自动审核驳回',
            '116' => '签名不合法，未带签名',
            '117' => 'IP地址认证错',
            '118' => '用户没有相应的发送权限',
            '119' => '用户已过期',
            '120' => '内容不是白名单',
        );
        $content = str_replace('“玩翻天”', '', $content);  //去掉玩翻天
        $content = urlencode($content);
        $urlTemplate2 = "http://222.73.117.158/msg/HttpBatchSendSM?account=wft168&pswd=Wft88666&msg={$content}&mobile={$phone}&needstatus=false";
        $output = file_get_contents($urlTemplate2);
        if (!$output) {
            echo self::$error = '第三方接口无数据返回';
            return false;
        }
        $result = preg_split("/[,\r\n]/", $output);

        if (isset($result[1])) {
            if ($result[1] == 0) {
                return true;
            } else {
                echo self::$error = $RemindMsg[$result[1]];
                return false;
            }

        } else {
            echo self::$error = "发生错误";
            return false;
        }


    }


    //得意2
    private static function Send_Message_Deyi_2($phone, $content)
    {
        $postdata = http_build_query(
            array(
                'channel' => 'wft',
                'pwd' => 'T^KOe#jB',
                'mobile' => $phone,//多个使用逗号，
                'message' => $content
            )
        );

        $opts = array('http' =>
            array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 30
            )
        );

        $context = stream_context_create($opts);

        $result = file_get_contents('http://pm.deyi.com', false, $context);

        $res_array = json_decode($result, true);
        if ($res_array['code'] == 0) {
            return true;
        } else {
            return false;
        }

    }
    /************************* 短信渠道结束 ***************************************/

}