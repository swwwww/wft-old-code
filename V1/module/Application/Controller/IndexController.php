<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Deyi\Baoyou\Baoyou;
use Deyi\BaseController;
use Deyi\PHPQrCode\QrCode;
use Deyi\PY;
use library\Service\System\Cache\RedCache;
use Deyi\Request;
use Deyi\SendMessage;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function aaAction(){
        $opts = 'version=5.0.0&encoding=UTF-8&certId=69798959224&txnType=01&txnSubType=01&bizType=000201&frontUrl=http%3A%2F%2Fapi.wanfantian.com%2Fweb%2Fnotify%2Funion&backUrl=http%3A%2F%2Fapi.wanfantian.com%2Fweb%2Fnotify%2Funion&signMethod=01&channelType=08&accessType=0&merId=898420148160500&orderId=WFT220826&txnTime=20160923183522&txnAmt=11000&currencyCode=156&reqReserved=%7B%22coupon_id%22%3A1824%2C%22coupon_name%22%3A%22%E5%85%A8%E6%98%8E%E6%98%9F%EF%BC%88%E5%AE%9C%E5%AE%B6%E5%BA%97%EF%BC%89%E6%BB%91%E5%86%B0%E4%BF%B1%E4%B9%90%E9%83%A8%22%2C%22buy_number%22%3A1%7D&signature=1K1c%2FfAFWWHA%2FEIMWqlWA2AlFtuuUJMYbDtuFHCww5aDqh4sfhAq9HxcT2m9usTum%2BkBjFx4HjzFJxQeOiAHexN5SNnnK41%2FThBX7L7Re1NG2nrNAcY47aroALNJOGina0rPhJi2PTg2JE6fMXjU4lUmJPXgiMLxYDiCIxoE0ae9A4fu9GjD8KVIvquwRkEqtMZoSNjJoVRbzNs9ZaNwJTwjjhRmPdFucMydZzRdZqn01rvBwVGljcnLt70uzB76LI5A85F%2BIkMcgB7bnbIwOw8a422XEyK4H7OLBpTgqNx%2F62qLXJN%2Fk50gO8hlfVylD2426xIw7Emk%2Fg67FLatRQ%3D%3D';
        $url='https://api.mch.weixin.qq.com/secapi/pay/refund';
//        $url='https://www.baidu.com';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//不验证证书
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);//不验证HOST
//        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
//            'Content-type:application/x-www-form-urlencoded;charset=UTF-8'
//        ));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opts);
//        curl_setopt($ch, CURLOPT_SSLVERSION , 3);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);

        /**
         * 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
         */
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // 运行cURL，请求网页
        $html = curl_exec($ch);

        var_dump($url,curl_errno($ch),curl_error($ch));exit;

        curl_close($ch);

//        $post_data=$opts;
//        if (is_array($post_data)) {
//            $post_data = http_build_query($post_data);
//        }
//        ini_set('default_socket_timeout',30);
//        $opts = array('http' =>
//            array(
//                'method' => "POST",
//                'header' => 'Content-type: application/x-www-form-urlencoded',
//                'content' => $post_data,
//                'timeout' => 30
//            )
//        );
//        $context = stream_context_create($opts);
//        $a= file_get_contents($url, false, $context);
//        var_dump($a);exit;

    }
    public function indexAction()
    {
        header("Location: http://wan.deyi.com/download/index.html");
        exit;
    }

    public function cleanRedisAction()
    {
        var_dump(RedCache::clearAll());
        exit;
    }

    //生成二维码
    public function phpqrcodeAction()
    {
        $code = $this->getQuery('code');
        $city = $this->getQuery('city');
        if (!$code) {
            return $this->_Goto('生成二维码的内容为空');
        }
        QrCode::png($code . '&city=' . $city);
        exit;
    }

    //设置mongodb index 索引
    public function setMongoIndexAction()
    {
//        $this->_getMdbSocialCircle()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialCircle()->ensureIndex(array('build_uid' => 1));
//        $this->_getMdbSocialCircle()->ensureIndex(array('status' => 1));
//        $this->_getMdbSocialCircle()->ensureIndex(array('type' => 1));
//
//        //add
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('cid' => 1));
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('uid' => 1));
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('status' => 1));
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('pid' => 1));
//        $this->_getMdbSocialCircleMsg()->ensureIndex(array('object_data.object_id' => 1));
        $this->_getMdbSocialCircleMsg()->ensureIndex(array('shop_id' => 1));
//
//
//        //add
//        $this->_getMdbSocialCircleMsgPost()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialCircleMsgPost()->ensureIndex(array('mid' => 1));
//        $this->_getMdbSocialCircleMsgPost()->ensureIndex(array('cid' => 1));
//        $this->_getMdbSocialCircleMsgPost()->ensureIndex(array('uid' => 1));
//        $this->_getMdbSocialCircleMsgPost()->ensureIndex(array('status' => 1));
//
//
//        //add
//        $this->_getMdbSocialCircleUsers()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialCircleUsers()->ensureIndex(array('uid' => 1));
//        $this->_getMdbSocialCircleUsers()->ensureIndex(array('cid' => 1));
//        $this->_getMdbSocialCircleUsers()->ensureIndex(array('status' => 1));
//        $this->_getMdbSocialCircleUsers()->ensureIndex(array('role' => 1));
//
//        //add
//        $this->_getMdbSocialPrise()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialPrise()->ensureIndex(array('uid' => 1));
//        $this->_getMdbSocialPrise()->ensureIndex(array('type' => 1));
//        $this->_getMdbSocialPrise()->ensureIndex(array('object_id' => 1));
//
//
//        //add
//        $this->_getMdbSocialChatMsg()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialChatMsg()->ensureIndex(array('from_uid' => 1));
//        $this->_getMdbSocialChatMsg()->ensureIndex(array('to_uid' => 1));
//        $this->_getMdbSocialChatMsg()->ensureIndex(array('status' => 1));
//        $this->_getMdbSocialChatMsg()->ensureIndex(array('new' => 1));
//
//        //add
//        $this->_getMdbSocialFriends()->ensureIndex(array('dateline' => -1));
//        $this->_getMdbSocialFriends()->ensureIndex(array('uid' => 1));
//        $this->_getMdbSocialFriends()->ensureIndex(array('like_uid' => 1));
//        $this->_getMdbSocialFriends()->ensureIndex(array('friends' => 1));


        echo "ok";
        exit;
    }

}
