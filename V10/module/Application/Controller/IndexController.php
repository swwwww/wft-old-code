<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Application\Module;
use Deyi\BaseController;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;
use library\Fun\Common;
use library\Service\Admin\Setting\Banner;
use library\Service\Order\Order;
use library\Service\System\Cache\RedCache;
use Deyi\Request;
use library\Service\System\Logger;
use library\Service\User\User;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Http\Response;
use Deyi\JsonResponse;


class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function indexAction()
    {
        return $this->jsonResponse(time());
    }

    //删除消息
    public function testAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }


        $uid = $this->getParams('uid');

        if (!$uid) {
            return $this->jsonResponse(array('status' => 0, 'message' => '用uid或操作对象不存在'));
        }


        // 数据库操作


        return $this->jsonResponse(array(
            'push_data' => array('img' => 'xxxx.jpg', 'name' => '撒的高手', ''),
            'list' => array()

        ));
    }

    //一些初始化数据
    public function initAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        //搜索默认关键字
        $data = $this->_getPlaySearchFormValueTable()->fetchAll(array('status' => 1, 'search_type' => 1), array('dateline' => 'desc'))->current();
        if ($data) {
            $defaultSearch = $data->val;
        } else {
            $defaultSearch = '';
        }

        $data_banner_list = Banner::getBannerListData(array(
            'banner_status' => 1,
            'banner_delete' => 0,
        ));

        if ($data_banner_list) {
            $data_city = $this->getCity();
            $data_temp_banner_setting_list = Banner::getBannerSettingListData(array(
                'banner_setting_city' => $data_city,
            ));

            $data_banner_setting_list = array();

            foreach ($data_temp_banner_setting_list as $key => $val) {
                $data_banner_setting_list[$val['banner_setting_banner_id']] = $val;
            }
        }

        $data = array(
            'defaultSearch' => $defaultSearch,
        );

        foreach ($data_banner_list as $key => $val) {
            $data['show_' . $val['banner_v_name']] = $data_banner_setting_list[$val['banner_id']]['banner_setting_value'] ? $data_banner_setting_list[$val['banner_id']]['banner_setting_value'] : 0;
        }

        return $this->jsonResponse($data);
    }

    //城市列表
    public function cityAction()
    {

        $data = array();

        $cityData = $this->_getPlayCityTable()->fetchAll(array('is_close' => 0, 'is_hot' => 1));


        /*$data['open_city'] = array(
            array(
                'name' => '武汉',
                'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
            ),
            array(
                'name' => '南京',
                'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
            ),
            array(
                'name' => '长沙',
                'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
            ),
        );*/
        $data['hot_city'] = array();
        foreach ($cityData as $city) {
            $data['hot_city'][] = array(
                'name' => $city->city_name,
                'city_img' => $this->_getConfig()['url'] . $city->city_img,
            );
        }

        /*$data['all_city'] = array(
            'C' => array(
                        array(
                            'name' => '长沙',
                            'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
                        ),
                    ),
            'N' => array(
                array(
                    'name' => '南京',
                    'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
                ),
            ),
            'W' => array(
                array(
                    'name' => '武汉',
                    'img' => $this->_getConfig()['url']. '/uploads/2015/12/22/d8d0104956feb1d6b5483006b2a73e3e.png',
                ),
            ),
        );*/


        return $this->jsonResponse($data);
    }

    //启动图
    public function bootAction()
    {

        $timer = time();
        $data = array(
            'time' => $timer,
            'end_time' => '',
            'app_phone' => '4008007221' //客服电话
        );
        $city = $this->getCity();
        $bootData = $this->_getPlayAttachTable()->fetchLimit(0, 3, array(), array('city' => $city, 'use_type' => 'firing', 'use_id' => 2, 'width < ?' => $timer, 'height > ?' => $timer), array('dateline' => 'desc'))->current();

        if ($bootData) {
            $data['boot_img']['index_2670_2001'] = $this->_getConfig()['url'] . json_decode($bootData->url)[2];
            $data['boot_img']['index_720_1280'] = $this->_getConfig()['url'] . json_decode($bootData->url)[1];
            $data['end_time'] = $bootData->height;
            $data['jump']     = 1;
            $data['jump_object'] = array(
                'id' => 0,
                'type' => 6,
                'url'  => 'http://play.wanfantian.com/member/guide',
            );
        }

        return $this->jsonResponse($data);
    }

    //意见反馈接口
    public function feedbackAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $message = $this->getParams('message');
        $phone = $this->getParams('phone');
        $uid = $this->getParams('uid');

        if (!$message) {
            return $this->jsonResponseError('请求内容不能为空');
        }
        $status = $this->_getPlayFeedbackTable()->insert(array('contact' => $phone, 'message' => $message, 'dateline' => time(), 'uid' => $uid));

        return $this->jsonResponse(array('status' => 1, 'message' => '添加成功'));

    }


    //检查版本接口
    public function versionsAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $device = $this->getParams('device'); //ios  or android


        if (!$device) {
            return $this->jsonResponseError('设备类型不存在');
        }
        if ($device == 'android') {

            $first = $this->_getPlayClientUpdateTable()->fetchLimit(0, 1, array(), array('status'=>1), array('id' => 'desc'))->toArray()[0];

            $old_update = 0;  //通过版本来区分

            //获取当前请求版本
            $client_info = Common::getClientinfo();
            if ($client_info['ver']) {
                $ver = sprintf('%-03s', str_replace('.', '', $client_info['ver']));
                $res = $this->_getPlayClientUpdateTable()->get(array('version_name' => $client_info['ver'],'status'=>1));
                if ($ver <= 331 or $res->old_update == 1) {  //小于3.3.1 强制升级
                    $old_update = 1;
                }
            } else {
                //老版本
                $old_update = 1;
            }
            $ver_data = array(
                'old_update' => $old_update,  //旧版本強制升级
                'version_code' => $first['version_code'],
                'version_name' => "V" . $first['version_name'],
                'packageurl' => $this->_getConfig()['url'] . "/download/wft-{$first['version_code']}.apk",
                'versioninfo' => $first['version_info']
            );

            //  $ver_data = $this->_getConfig()['version'];
            return $this->jsonResponse($ver_data);
        } else {
            //ios
            $data = RedCache::fromCacheData("D:checkver:ios", function () {
                return  file_get_contents('http://itunes.apple.com/lookup?id=950652997');
            }, 600, false);

            $arr = json_decode($data, true);
            if (!empty($arr)) {
                $msg = $arr['results'][0]['releaseNotes'];
                $version = $arr['results'][0]['version'];
                $array = array(
                    'version' => $version,
                    'url' => 'http://www.deyi.com/app/deyi.plist', // 待定
                    'msg' => $msg,
                );

                if (!$array['version']) {
                    $array = array(
                        'version' => '1.0',
                        'url' => 'http://www.deyi.com/app/deyi.plist', // 待定
                        'msg' => '初始版本',
                    );
                }


                return $this->jsonResponse($array);
            } else {
                return $this->jsonResponseError('请求apple服务器失败，请稍后再试');
            }


        }
    }

    //IOS  企业升级
    public function companyAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }

       
        //ios
        $data =  Request::get('http://itunes.apple.com/lookup?id=950652997');
        $arr = json_decode($data, true);
        if (!empty($arr)) {
            $msg = $arr['results'][0]['releaseNotes'];
            $version = $arr['results'][0]['version'];
            $array = array(
                'version' => $version,
                'msg' => $msg,
            );
        } else {
            return $this->jsonResponseError('请求apple服务器失败，请稍后再试');
        }

        if (!$version) {
            $array = array(
                'version' => '10.1',  //默认10.1为忽略版本
                'msg' => '',
            );
        }

        return $this->jsonResponse($array);

    }

    //分享接口
    public function shareAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }

        $type = $this->getParams('type', 'coupon'); // coupon or activity or tag or shop or game or webview or my or word
        $uid = $this->is_weixin() ? $_COOKIE['uid'] : $this->getParams('uid', 1);
        $share_id = $this->getParams('share_id', 1);
        $share_app = $this->getParams('share_app', 0);
        $city = $this->getCity();

        if($this->is_weixin() and !$uid){
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));  //用户未登录
        }
        //处理客户端bug
        if ($type == 'goods') {
            $type = 'game';
        }

        if ($type == 'webview') {
            $share_id = 987;
        }

        if (!$uid or !$share_id) {
            //Logger::writeLog("分享回调错误:".print_r($_SERVER,true)."uid: {$uid},share_id:{$share_id},,type:{$type}");
            return $this->jsonResponseError('参数错误');
        }

        if (!$type) {
            $type = 'play';
        }

        if ($type == 'game') {
            $integral = new Integral();
            $integral->share_good_integral($uid, $share_id, $city);
        }

        if ($type == 'shop') {
            $integral = new Integral();
            $integral->share_place_integral($uid, $share_id, $city);
        }

        if ($type == 'buygoods') {
            $invite = new Invite();
            $invite->shareCash($uid, (int)$share_id, $city);
        }

        if ($type == 'buyactive') {
            $invite = new Invite();
            $invite->shareExcercise($uid, (int)$share_id, $city);
        }

        if ($type == 'lottery') {

        }

        $status = $this->_getPlayShareTable()->insert(array('uid' => $uid, 'type' => $type, 'share_id' => $share_id, 'dateline' => time(), 'share_app' => $share_app));
        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功'));
        }
        return $this->jsonResponse(array('status' => 0, 'message' => '操作失败'));

    }
}
