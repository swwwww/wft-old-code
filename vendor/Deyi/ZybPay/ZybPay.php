<?php
namespace Deyi\ZybPay;

use Deyi\getServerConfig;
use Deyi\BaseController;
use Application\Module;

class ZybPay
{

    use BaseController;

    public $free_config;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /*登录网址：http://boss.zhiyoubao.com/boss/login.htm
    用户名：wftkj
    密码：svytyzj3
    企业码：sdzfxwftkj
    企业私钥：086111FE327503DD4EF9B4B3A646FE19
    */

    //定义回调地址
    /*//退票通知
    http://api.wanfantian.com/web/notify/zybBackTicket

    //订单完结通知
    http://api.wanfantian.com/web/notify/zybFinishOrder

    //订单核销通知
    http://api.wanfantian.com/web/notify/zybFinishTicket*/

    //正式环境 通知地址
    /*boss.zhiyoubao.com/boss/service/code.htm*/

    function __construct()
    {
        $this->free_config = array(
            /*'front_notify_url' => 'http://mengxj.sendinfo.com.cn/boss/service/code.htm', //测试地址
            'free_name' => 'admin',
            'free_password' => 'TESTFX',
            'free_private_password' => 'TESTFX',*/
            'front_notify_url' => 'http://boss.zhiyoubao.com/boss/service/code.htm', //正式地址
            'free_name' => 'wftkj',
            'free_password' => 'sdzfxwftkj',
            'free_private_password' => '086111FE327503DD4EF9B4B3A646FE19',
        );
    }

    /**
     * 智游宝下单
     * @param $order_sn
     * @return array
     */
    public function pay($order_sn) {

        $config = $this->free_config;
        $account_type = 'vm'; //spot现场支付vm备佣金zyb智游宝支付
        $order_sn = (int)$order_sn;

        $adapter = $this->_getAdapter();
        $order_data = $adapter->query(
            "SELECT play_order_info.*, play_order_otherdata.message FROM play_order_info
             LEFT JOIN play_order_otherdata ON play_order_otherdata.order_sn = play_order_info.order_sn
             WHERE  play_order_info.order_sn = ?",
             array($order_sn)
        )->current();
        $good_info_data = $adapter->query(
            "SELECT play_game_info.*, play_order_info_game.start_time as use_time FROM play_game_info
             LEFT JOIN  play_order_info_game ON play_order_info_game.game_info_id = play_game_info.id
             WHERE play_order_info_game.order_sn = ?",
             array($order_sn)
        )->current();

        $data_organizer_game = $this->_getPlayOrganizerGameTable()->get(['id' => $order_data->coupon_id]);

        if (!$order_data || !$good_info_data || $order_data->order_status != 1 || $order_data->pay_status < 2) {
            return array('status' => 0, 'message' => '订单异常');
        }

        $order_pay = bcadd($order_data->real_pay, $order_data->account_pay, 2);
        $user_name = $order_data->buy_name;
        $user_phone = $order_data->buy_phone;
        $ticket_price = $good_info_data->money;
        $remark = $order_data->message;
        $good_sm = $good_info_data->goods_sm;
        $codeXml = array();
        $code_data = $this->_getPlayCouponCodeTable()->fetchAll(array('order_sn' => $order_sn));

        if ($data_organizer_game->need_use_time == 2) {
            $occDate = date("Y-m-d", $good_info_data->use_time);
        } else {
            $occDate = 0;
            //兼容时间
            if (in_array($good_sm, array('20141126021218', '20141126021225', 'PST2015061001224', 'PST20150616013069'))) {
                $occDate = date('Y-m-d', time() + 86400);
            }
        }

        foreach ($code_data as $code) {
            $codeXml[] = array(
                'ticketOrder' => array(
                    'orderCode' => $code->id . $code->password,
                    'price' => $ticket_price,
                    'quantity' => 1,
                    'totalPrice' => $order_data->coupon_unit_price,
                    'occDate' => $occDate ? $occDate : date('Y-m-d', time()), //当前时间下单
                    'goodsCode' => $good_sm,
                    'goodsName' => $order_data->coupon_name,
                    'remark' => $remark
                ),
            );
        }

        $xmlArr = array(
            'PWBRequest' => array(
                'transactionName' => 'SEND_CODE_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'order' => array(
                        'certificateNo' => '',
                        'linkName' => $user_name,
                        'linkMobile' => $user_phone,
                        'orderCode' => 'WFT'. $order_sn,
                        'orderPrice' => $order_pay,
                        'src' => '',
                        'groupNo' => '',
                        'payMethod' => $account_type,
                        'ticketOrders' => $codeXml,
                    ),
                ),
            ),
        );

        $xmlMsg = $this->arrayToXml($xmlArr);

        if (is_array($xmlMsg)) {
            return $xmlMsg;
        }

        $result = $this->getResult($xmlMsg);

        return $result;
    }

    /**
     * 智游宝查询检票情况
     * @param $order_sn
     * @return array|mixed|string
     */

    public function checkTicket($order_sn) {

        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'CHECK_STATUS_QUERY_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'order' => array(
                        'orderCode' => 'WFT'. $order_sn,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;

    }

    /**
     * 智游宝部分退票
     * @param $code_id //使用码id
     * @return array|mixed|string
     */

    public function backPartTicket($code_id) {

        //todo 退票情况查询 需要  退单批次号 retreatBatchNo
        $adapter = $this->_getAdapter();
        $order_data = $adapter->query("SELECT play_coupon_code.*, play_order_info.order_status, play_order_info.pay_status FROM play_coupon_code LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE  play_coupon_code.id = ?", array($code_id))->current();
        if ($order_data->order_status != 1 || $order_data->pay_status < 2 || $order_data->status != 0) {
            return array('status' => 0, 'message' => '订单异常');
        }

        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'RETURN_TICKET_NUM_NEW_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'returnTicket' => array(
                        'orderCode' => $order_data->id. $order_data->password,
                        'returnNum' => 1,
                        'thirdReturnCode' => 'WFT'. $order_data->order_sn,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;

    }

    /**
     * 订单查询
     * @param $order_sn
     * @return array|mixed|string
     */
    public function findOrderInfo($order_sn) {
        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'QUERY_ORDER_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'order' => array(
                        'orderCode' => 'WFT'. (int)$order_sn,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;
    }

    /**
     * 订单检票查询
     * @param $order_sn
     * @return array|mixed|string
     */
    public function orderCheck($order_sn) {

        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'QUERY_SUB_ORDER_CHECK_RECORD_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'order' => array(
                        'orderCode' => 'WFT'. $order_sn,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;
    }


    /**
     * 退票情况查询
     * @param $back_number 退单批次
     * @return array|mixed|string
     */
    public function backTicketInfo($back_number) {

        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'QUERY_RETREAT_STATUS_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'order' => array(
                        'retreatBatchNo' => $back_number,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;

    }

    /**
     * 分销商改签
     * @param $code_id
     * @param $new_date
     * @return array|mixed|string
     */
    public function alterTicket($code_id, $new_date) {

        $adapter = $this->_getAdapter();
        $order_data = $adapter->query("SELECT play_coupon_code.*, play_order_info.order_status, play_order_info.pay_status FROM play_coupon_code LEFT JOIN play_order_info ON play_order_info.order_sn = play_coupon_code.order_sn WHERE  play_coupon_code.id = ?", array($code_id))->current();
        if ($order_data->order_status != 1 || $order_data->pay_status < 2 || $order_data->status != 0) {
             return array('status' => 0, 'message' => '订单异常');
        }

        $config = $this->free_config;

        $data = array(
            'PWBRequest' => array(
                'transactionName' => 'ORDER_ENDORSE_REQ',
                'header' => array(
                    'application' => 'SendCode',
                    'requestTime' => date('Y-m-d H:i:s', time()),
                ),
                'identityInfo' => array(
                    'corpCode' => $config['free_password'],
                    'userName' => $config['free_name'],
                ),
                'orderRequest' => array(
                    'endorse' => array(
                        'subOrderCode' => $order_data->id. $order_data->password,
                        'newOccDate' => $new_date,
                    ),
                ),
            ),
        );

        $xmlString = $this->arrayToXml($data);

        if (is_array($xmlString)) {
            return $xmlString;
        }

        $result = $this->getResult($xmlString);

        return $result;
    }


    /**
     * 获取订单信息 并更新时间
     * @param $order_sn
     * @return bool|int
     */
    public function getOrderInfo($order_sn) {

        $result = false;

        $Adapter = $this->_getAdapter();
        $res = $this->findOrderInfo($order_sn);
        if ($res['code'] == 0 && $res['description'] == '成功') {
            //这儿 以后不同日期 可以 不同处理
            if (isset($res['order']['scenicOrders']['scenicOrder'][0])) {
                $valueData = $res['order']['scenicOrders']['scenicOrder'][0];
            } else {
                $valueData = $res['order']['scenicOrders']['scenicOrder'];
            }

            $result = $Adapter->query('UPDATE play_zyb_info SET play_start_time = ?, play_end_time = ? WHERE order_sn = ?',array(strtotime($valueData['startDate']), strtotime($valueData['endDate']), $order_sn))->count();

        }

        return $result;

    }

    /**
     * //todo 未处理
     * 订单完结通知 回调url
     * ①当取消订单时：(就是整个订单全部退票一张没捡  全部退票) ② 检票完成：
     */



    /**
     * 回调签名
     * @param $order_sn
     * @param $type //类型 检票(核销)回调  退票回调
     * @return string
     */
    public function checkSign($order_sn, $type = 'checkTicket') {
        $config = $this->free_config;
        $sign = '';
        if ($type == 'checkTicket') {
            $sign = md5('order_no='. $order_sn. $config['free_private_password']);
        }

        if ($type == 'backTicket') {
            $sign = md5($order_sn. $config['free_private_password']);
        }

        return $sign;
    }

    /**
     * 数组转化为 智游宝需要的 XML格式字符串
     * @param $params
     * @return array|string
     */
    private function arrayToXml($params) {

        if (!is_array($params) || !count($params)) {
            return array('status' => 0, 'message' => '参数不正确');
        }

        $xmlString =  '';

        foreach ($params as $key => $val) {
            if (!is_numeric($key)) {
                if (!is_array($val)) {
                    $xmlString .= "<".$key.">". $val. "</".$key.">";
                } else {
                    $value = $this->arrayToXml($val);
                    $xmlString .= "<" . $key . ">" . $value . "</" . $key . ">";
                }
            } else {
                $value = $this->arrayToXml($val);
                $xmlString .= $value;
            }
        }

        return $xmlString;
    }

    /**
     *  向智游宝发送请求
     * @param $xmlString
     * @return mixed
     */
    private function getResult($xmlString) {

        $config = $this->free_config;
        $sign = MD5('xmlMsg='. $xmlString. $config['free_private_password']);
        $xml = http_build_query(array("xmlMsg" => $xmlString,"sign" => $sign));
        $res_xml = $this->postXmlfile_get_contents($xml, $config['front_notify_url']);
        $res =  $this->xmlToArray($res_xml);
        return $res;
    }

    /**
     * @param $xml
     * @param $url
     * @param int $second
     * @return string
     */
    public static function postXmlfile_get_contents($xml, $url, $second = 60){
        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded",
                'content' => $xml,
                'timeout' => $second
            )
        );
        $context  = stream_context_create($opts);
        $res = file_get_contents($url, false, $context);
        return $res;
    }

    /**
     * 作用：将xml转为array
     * @param $xml
     * @return mixed
     */
    private function xmlToArray($xml) {

        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }


}
