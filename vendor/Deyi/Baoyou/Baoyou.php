<?php

/**
 * 保游网保险产品接口
 *
 * //        $baoyou = new Baoyou();
 * //        var_dump($baoyou->Cins("23101015069900160000134", "BX2016033110472600016"));
 * //        file_put_contents($_SERVER['DOCUMENT_ROOT'].'/TSET.PDF',base64_decode($aa['Data']));
 * //        var_dump($baoyou->GetProductList());
 * //        var_dump($baoyou->Ins());
 * //        var_dump ($baoyou->GetProductRateList('5051d73d65634431a9f322ff06448456'));
 */
namespace Deyi\Baoyou;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;
use Deyi\Request;
use Zend\Code\Reflection\DocBlock\Tag\ReturnTag;

class Baoyou
{
    use BaseController;

    private $url;
    private $userid;
    private $password;
    private $token;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public function __construct()
    {
        $this->url = $this->_getConfig()['baoyou']['url'];
        $this->userid = $this->_getConfig()['baoyou']['userid'];
        $this->password = $this->_getConfig()['baoyou']['password'];


    }

    //通过code 获取对应的天数和价格
    public function getProductInfo($product_code)
    {
        $baoyoulist = json_decode($this->GetProductRateList()['Data'], true);


        $baoyou_data = false;
        foreach ($baoyoulist as $vv) {
            if ($vv['RateCode'] == $product_code) {
                $baoyou_data = $vv;
                break;
            }
        }

        if ($baoyou_data) {
            return $baoyou_data;
        } else {
            return false;
        }
    }

    //产品基本信息同步 (保险类型列表)
    public function GetProductList($pageIndex = 1, $pageSize = 20)
    {
        /*
            Isjw	int	是否境外	10,是  20,否
            Isjssx	int	是否即时生效	10,是  20,否
        */
        // 获取产品数据列表
        $_url = $this->url . "/ApiProduct/v2/GetProductList";
        $param = "token=" . urlencode($this->getToken()) . "&pageIndex=" . $pageIndex . "&pageSize=" . $pageSize;
        $output = $this->request_https($_url, $param);
        return json_decode($output, true);
    }

    //产品费率同步
    public function GetProductRateList($productGuid = '', $pageIndex = 1, $pageSize = 20)
    {
        /* 返回固定的数据 */
        $data = <<<HTML
{"TotalCount":6,"IsSuccess":true,"Data":"[{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543173\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"1\",\"DayType\":10.0,\"Premium\":1.80,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30},{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543174\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"2-5\",\"DayType\":10.0,\"Premium\":3.00,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30},{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543175\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"6-10\",\"DayType\":10.0,\"Premium\":5.00,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30},{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543176\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"11-15\",\"DayType\":10.0,\"Premium\":10.00,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30},{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543177\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"16-20\",\"DayType\":10.0,\"Premium\":15.00,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30},{\"PlanGuid\":\"ec55967c0c1d453786f36e57e2406e5f\",\"ProductName\":\"\u201c\u4fdd\u6e38\u5929\u4e0b\u201d\u62d3\u5c55\u8bad\u7ec3\u4fdd\u969c\u8ba1\u5212\",\"PlanName\":\"\u6807\u51c6\u578b                                               \",\"RateCode\":\"BY37543178\",\"AgeRange\":\"1-80\u5468\u5c81\",\"DayRange\":\"21-30\",\"DayType\":10.0,\"Premium\":25.00,\"BeginAge\":1,\"BAgeType\":30,\"EndAge\":80,\"EAgeType\":30}]","MsgCode":60000,"ErrorMsg":null}
HTML;
        return json_decode($data, true);


        /********* 动态获取 *********/
//        if(!$productGuid){
//            $productGuid=$this->_getConfig()['baoyou']['productGuid'];
//        }
//        return RedCache::fromCacheData("D:GetProductRateList:{$productGuid}", function () use ($productGuid, $pageIndex, $pageSize) {
//            // 获取产品费率数据列表
//            $_url = $this->url . "/ApiProduct/v2/GetPlanRateList";
//            $param = "token=" . urlencode($this->getToken()) . "&planGuid=" . $productGuid . "&pageIndex=" . $pageIndex . "&pageSize=" . $pageSize;
//            $output = $this->request_https($_url, $param);
//            return json_decode($output, true);
//        }, 3600 * 24 * 20, true);

    }

    //投保
    function Ins($param)
    {


        /*
         * stdClass Object ( [PolicyNo] => 23101015069900160000131 [OrderNo] => BX2016033109524600013 [TotalPremium] => 0 [IsSuccess] => 1 [Data] => 操作成功 [MsgCode] => 10000 [ErrorMsg] => ) NULL
        */
//
//        $param = array(
//            'Order' => array( //保单基本信息
//                'ProductCode' => "BY5811715004",  //保游网提供的产品编号
//                'SerialNumber' => 'WFT0003435',   //合作伙伴生成的订单唯一
//                'StartTime' => "2016-04-01 00:00:00",  //保险开始时间
//                'EndTime' => "2016-04-01 23:59:59", //保险结束时间
//                'Destination' => '中国'  // 出行目的地
//            ),
//            'PolicyHolder' => array( //投保人信息
//                'CName' => 'justin_test', //投保人中文名
//                'EName' => 'Justin1', //投保人姓名拼音
//                'CardType' => 1,  //1身份证;2护照;3其他
//                'Sex' => 1,  //0：女 1：男
//                'CardNo' => '420281199211207633',  //投保人证件号码[身份证号码只需要支持18位，15位身份证已经过期]
//                'Mobile' => '15994225894', //投保人手机号码 送投保成功短信
//                'BirthDay' => "1992-11-20"// 投保人出生日期	格式yyyy-MM-dd[投保人必须年满18周岁]
//            ),
//            'Insureds' => array(//被保险人信息 被保人可以传多
//                array(
//                    'CName' => 'justin_test',
//                    'EName' => 'Justin1',
//                    'CardType' => 2,
//                    'Sex' => 1,
//                    'CardNo' => '420281199211207633',
//                    'Mobile' => '15994225894',
//                    'BirthDay' => '1992-11-20'
//                )
//            )
//        );

        $jsonStr = json_encode($param);
        $sign = md5($this->getToken() . $jsonStr);
        $text = "token=" . urlencode($this->getToken()) . "&jsonParam=" . urlencode($jsonStr) . "&sign=" . $sign;
        $_url = $this->url . "/Insurance/Insuran";
        $output = $this->request_https($_url, $text);

        return json_decode($output);
    }

    //退保  保单号  保游网订单号
    function Cins($policyNo, $orderNo)
    {
        //未生效的保单可以退款
        //保险产品生效时间是第二天零点生效。
        //有即时生效产品。购买立马生效

        $_url = $this->url . "/Insurance/CancelIns";
        $data = array(
            'PolicyNo' => $policyNo,
            'OrderNo' => $orderNo
        );
        $jsonStr = json_encode($data);
        $sign = md5($this->getToken() . $jsonStr);
        $text = "token=" . urlencode($this->getToken()) . "&jsonParam=" . urlencode($jsonStr) . "&sign=" . $sign;
        $output = $this->request_https($_url, $text);
        return json_decode($output);
    }

    //保单下载
    public function DownLoadPolicy($policyNo, $orderNo)
    {

        $_url = $this->url . "/Insurance/DownLoadPolicy";
        $data = array(
            'PolicyNo' => $policyNo,
            'OrderNo' => $orderNo
        );
        $jsonStr = json_encode($data);
        $sign = md5($this->getToken() . $jsonStr);
        $text = "token=" . urlencode($this->getToken()) . "&jsonParam=" . urlencode($jsonStr) . "&sign=" . $sign;
        $output = $this->request_https($_url, $text);
        return json_decode($output, true);
        /*
        * file_put_contents($_SERVER['DOCUMENT_ROOT'].'/TSET.PDF',base64_decode($aa['Data']));
        *
        * */

    }

    //获取token
    public function getToken()
    {
        /*
         ["AccessToken"]=>
  string(28) "uAMDAcQX13XaM9Qy6c6IUBphmkw="
  ["RefreshToken"]=>
  string(28) "y5VXPwH1B+RHRaT48/Ca/IOQsN4="
        */
        $tokenData = RedCache::fromCacheData('D:baoyoutoken', function () {
            $res = $this->request_https($this->url . "/Oauth/access_token?userid={$this->userid}&Password={$this->password}");
            if ($res) {
                $res = json_decode($res, true);
                if ($res['IsSuccess'] == true) {
                    return $res;
                }
            }
            return 0;
        }, 3600 * 24 * 6, true);

        if ($tokenData) {
            return $tokenData['AccessToken'];
        } else {
            RedCache::del('D:baoyoutoken');
            exit('获取保游网token失败,请重试');
        }
    }

    //刷新token
    public function getRefreshTokenData($refresh_token)
    {
        return $this->request_https($this->url . '/Oauth/refresh_token?refresh_token=' . $refresh_token);
    }

    public function request_https($url, $data = array(), $timeout = 60)
    {
        if (!empty($data)) {
            $res = Request::post($url, $data, $timeout);
        } else {
            $res = Request::get($url, $timeout);
        }
        return $res;
    }
}