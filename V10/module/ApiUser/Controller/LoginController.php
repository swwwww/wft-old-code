<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiUser\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\getServerConfig;
use library\Fun\Common;
use library\Fun\M;
use library\Service\ServiceManager;
use library\Service\System\Cache\RedCache;
use Deyi\Request;
use Deyi\SendMessage;
use Deyi\WriteLog;
use library\Service\System\Logger;
use Zend\Db\Sql\Select;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\Invite\Invite;

class LoginController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    protected $appid = 1103475928;
    protected $username = '';
    protected $uid;
    protected $token;
    protected $unionid;
    protected $img;
    protected $error = '';
    protected $DeyiApiUrl = 'https://api.deyi.com';
    private $secret;

    public function __construct()
    {
        $this->secret = getServerConfig::get('deyiSecret');

    }


    //手机号注册/登录
    public function registerAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $code = $this->getParams('code');//验证码
        $phone = $this->getParams('phone');  //手机号
        $device = $this->getParams('device');//设备类型
        $user_source=$this->getParams('user_source',''); //用户来源
        
        $code=$this->replaceAllSpace($code);

        $city = $this->getCity();

        if (!$phone or !$code) {
            return $this->jsonResponseError('参数错误');
        }

        $pass_list =ServiceManager::getConfig('user_pass_list');


        if ((!Common::isUp() or in_array($phone,$pass_list)) and  $code == '123456') {
            //todo 直接通过
        } else {
            //验证码
            if (!$this->check_auth_code($phone, $code)) {
                return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
            }
        }

        $userdata = $this->_getPlayUserTable()->fetchAll(array('phone' => $phone),array('uid'=>'desc'),1)->current();

        if ($userdata) {
            //update token
            $this->_getPlayUserTable()->update(array('token' => md5(md5($code) . time())), array('uid' => $userdata->uid));

            //登录
            $res = $this->backuserinfo($userdata->uid);
            return $this->jsonResponse($res);

        } else {
            //注册
            $token = md5(md5($code) . time());
            $status = $this->_getPlayUserTable()->insert(array(
                'username' => $phone,
                'token' => $token,
                'mark_info' => 0,
                'phone' => $phone,
                'login_type' => 'phone',
                'is_online' => 1,
                'device_type' => $device,
                'dateline' => time(),
                'status' => 1,
                'password' => '',
                'city' => $this->getCity(),
                'source'=>$user_source
            ));

            $uid = $this->_getPlayUserTable()->getlastInsertValue();
            if ($status) {
                $this->use_auth_code($phone, $code);

                // 发资格券
                $coupon = new Coupon();
                $coupon->addQualify($uid, 4, $city, 0, 0, 0);

                //TODO 注册 邀约 注意：先发放邀请人积分,再发放受邀人票券
                $invite = new Invite();
                
                $invite->InvitorAwardByRegister($uid,$phone,$city);
                $invite->inviteRegister($uid,$phone,$phone,$city);


                /**** 发送欢迎消息 *****/
                $this->sendMes($uid);
                $this->_getPlayUserTable()->update(array('is_online' => 1, 'device_type' => $device), array('uid' => $uid));

                $res = $this->backuserinfo($uid);
                return $this->jsonResponse($res);
            } else {
                return $this->jsonResponse(array('status' => 0, 'message' => '数据插入失败'));
            }

        }
    }

    //微信登陆注册
    public function inAction()
    {

        if (!$this->pass()) {
            return $this->failRequest();
        }


        $token = $this->getParams('token');
        $markid = $this->getParams('open_id');
        $device = $this->getParams('device'); //ios  or  android
        $baidu_push_id = $this->getParams('push_id');


        if (!$markid or !$token or !in_array($device, array('ios', 'android'))) {
            return $this->jsonResponseError('请求参数错误');
        }

        $login = false;

        if ($this->check_weixin_login($token, $markid)) {
            $login = true;
        }


        if (!$login) {
            return $this->jsonResponse(array('status' => 0, 'message' => 'ERROR：' . $this->error));
        }

        /************* 验证成功 *************/

//        $unionid = $this->unionid;  使用unionid登陆
//
//        //最大可能有3条用户数据
//        $userdata = $this->_getPlayUserWeiXinTable()->tableGateway->select(function (Select $select) use ($unionid, $markid) {
//            $select->join('play_user', 'play_user.uid=play_user_weixin.uid', '*', 'left');
//            $select->where("(play_user_weixin.unionid= '{$unionid}' or (play_user.mark_info='{$markid}' and play_user.login_type='weixin')) and !ISNULL(phone)");
//            $select->limit(1);
//        })->current();

        $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('open_id' => $markid, 'login_type' => 'weixin_sdk'));


        if ($weixin_data) {
            $userdata = $this->_getPlayUserTable()->get(array('uid' => $weixin_data->uid));
        } else {
            $userdata = $this->_getPlayUserTable()->get(array('mark_info' => $markid, 'login_type' => 'weixin'));
        }


        if ($userdata) {
            if ($userdata->phone) {
                $this->uid = $userdata->uid;
                $this->token = $token;

                //设置用户为在线
                if ($userdata->img) {
                    $newData = array(
                        'token' => md5(md5($token) . time()),
                        'is_online' => 1,
                        'push_id' => $baidu_push_id,
                        'device_type' => $device
                    );
                } else {
                    $newData = array(
                        'username' => $this->username,
                        'img' => $this->img,
                        'token' => md5(md5($token) . time()),
                        'is_online' => 1,
                        'push_id' => $baidu_push_id,
                        'device_type' => $device
                    );
                }

                $this->_getPlayUserTable()->update($newData, array('uid' => $userdata->uid));

                // 更改用户的头像
                $this->_getMdbSocialCircleMsg()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
                $this->_getMdbSocialCircleMsgPost()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
                $this->_getMdbSocialCircleUsers()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true));


                $res = $this->backuserinfo($this->uid);

                $this->initWeiXinData($userdata->uid, $markid, $this->unionid, $token);

                return $this->jsonResponse($res);


            } else {
                return $this->jsonResponse(array('status' => 2, 'message' => '未绑定手机号'));
            }
        } else {
            return $this->jsonResponse(array('status' => 2, 'message' => '未绑定手机号'));
        }


    }

    //微信SDK 绑定手机号
    public function sdkbindAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }
        $city = $this->getCity();
        $code = $this->getParams('code');//验证码
        $phone = $this->getParams('phone');

        $token = $this->getParams('token');
        $openid = $this->getParams('open_id');
        $device = $this->getParams('device'); //ios  or  android
        $baidu_push_id = $this->getParams('push_id');


        $login_type = 'weixin_sdk';
        if (!$phone or !$code) {
            return $this->jsonResponseError('参数错误');
        }


        //验证码
        if (!$this->check_auth_code($phone, $code)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
        }


        $weixin_data = $this->check_weixin_login($token, $openid);
        if (!$weixin_data) {
            return $this->jsonResponse(array('status' => 0, 'message' => '账号验证失败'));
        }

        // 检查此微信是否绑定其他账号
        $log = $this->_getPlayUserWeiXinTable()->get(array(
            'open_id' => $openid,
            'login_type' => 'weixin_sdk', //微信授权表改为通用授权表
        ));
        if ($log) {
            return $this->jsonResponseError('此微信已绑定uid为' . $log->uid . '的账号');
        }


        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if ($user_data) {
            //用户存在直接绑定
            $s = $this->initWeiXinData($user_data->uid, $openid, $weixin_data->unionid, $token);
            if ($s) {
                $uid = $user_data->uid;
            } else {
                return $this->jsonResponse(array('status' => 0, 'message' => '登录失败'));
            }
        } else {

            //phone对用的用户不存在 查询微信用户为否存在,不存在新建用户
            $u = $this->_getPlayUserTable()->get(array('mark_info' => $openid, 'login_type' => 'weixin'));
            if (!$u) {
                //注册用户
                $status = $this->_getPlayUserTable()->insert(array(
                    'is_online' => 1,
                    'push_id' => $baidu_push_id,
                    'username' => $this->username,
                    'token' => md5(md5($token) . time()),
                    'mark_info' => $openid,
                    'login_type' => 'weixin',
                    'device_type' => $device,
                    'status' => 1,
                    'dateline' => time(),
                    'phone' => $phone,
                    'img' => $this->img,
                    'city' => $city,
                ));

                if ($status) {
                    $uid = $this->_getPlayUserTable()->getlastInsertValue();
                    /**** 发送欢迎消息 *****/
                    //TODO 注册 邀约 注意：先发放邀请人积分,再发放受邀人票券
                    $invite = new Invite();
                    $invite->InvitorAwardByRegister($uid, $phone, $city);
                    $invite->inviteRegister($uid, $this->username, $phone, $city);

                    $this->sendMes($uid);
                } else {
                    return $this->jsonResponseError('插入数据失败');
                }

            } else {
                $uid = $u->uid;
            }
            $s = $this->initWeiXinData($uid, $openid, $weixin_data->unionid, $token);

            if (!$s) {
                return $this->jsonResponseError('操作失败');
            }

        }

        $this->use_auth_code($phone, $code);

        /**************** 登录成功 ******************/
        $this->uid = $uid;
        $this->token = $token;

        //设置用户为在线
        $this->_getPlayUserTable()->update(array('username' => $this->username, 'img' => $this->img, 'token' => $token, 'is_online' => 1, 'push_id' => $baidu_push_id, 'device_type' => $device), array('uid' => $this->uid));

        // 更改用户的头像
        $this->_getMdbSocialCircleMsg()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
        $this->_getMdbSocialCircleMsgPost()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
        $this->_getMdbSocialCircleUsers()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true));
        $res = $this->backuserinfo($uid);
        return $this->jsonResponse($res);
    }


    //手机号 绑定微信SDK　（绑定有礼）
    public function bindsdkAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }


        $uid = $this->getParams('uid', 0);
        $token = $this->getParams('access_token');
        $openid = $this->getParams('open_id');


        $city = $this->getCity();


        if (!$uid) {
            return $this->jsonResponseError('uid不存在');
        }

        $login_type = 'weixin_sdk';
        if (!$token or !$openid) {
            return $this->jsonResponseError('微信参数错误');
        }


        $weixin_data = $this->check_weixin_login($token, $openid);
        if (!$weixin_data) {
            return $this->jsonResponse(array('status' => 0, 'message' => '账号验证失败'));
        }

        // 检查此微信是否绑定其他账号
        $log = $this->_getPlayUserWeiXinTable()->get(array(
            'open_id' => $openid,
            'login_type' => 'weixin_sdk', //微信授权表改为通用授权表
        ));
        if ($log) {
            return $this->jsonResponseError('此微信已绑定uid为' . $log->uid . '的账号');
        }


        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$user_data->phone) {
            return $this->jsonResponseError('用户手机号不存在!');
        }

        if ($user_data) {
            //用户存在直接绑定
            $s = $this->initWeiXinData($user_data->uid, $openid, $weixin_data->unionid, $token);
            if ($s) {
                $uid = $user_data->uid;
            } else {
                return $this->jsonResponse(array('status' => 0, 'message' => '登录失败'));
            }
        } else {
            return $this->jsonResponseError('用户不存在!');
        }


        /**************** 绑定成功 ******************/
        $this->uid = $uid;
        $this->token = $token;

        // 更改用户的头像
//        $this->_getMdbSocialCircleMsg()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
//        $this->_getMdbSocialCircleMsgPost()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true)); // 用户名称 与 alias
//        $this->_getMdbSocialCircleUsers()->update(array('uid' => (int)$this->uid), array('$set' => array('img' => $this->getImgUrl($this->img))), array('multiple' => true));
//        $res = $this->backuserinfo($uid);
        //微信绑定成功积分奖励
        $integral = new Integral();
        $integral->weixin_bind($uid, $city);
        return $this->jsonResponse(array('status' => 1, 'message' => '绑定成功'));
    }

    //微信wap 绑定手机号
    public function bindphoneAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $code = $this->getParams('code');//验证码
        $phone = $this->getParams('phone');
        $uid = $this->getParams('uid');
        $is_weixin_wap = $this->getParams('is_weixin', false);
        if (!$phone or !$code or !$uid) {
            return $this->jsonResponseError('参数错误');
        }

        //验证码
        if (!$this->check_auth_code($phone, $code)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
        }
        $status = false;
        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if ($user_data) {
            //将此微信号绑定到对应手机号用户
            if ($is_weixin_wap) {
                $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid)); //微信授权相关信息
                $status = $this->_getPlayUserWeiXinTable()->update(array('uid' => $user_data->uid), array('uid' => $uid)); //关联到新的uid
                $this->setCookie($user_data->uid, $user_data->token, $weixin_data->open_id, $phone); //新数据  // 需要保留token与客户端相同
            }

        } else {
            //直接绑定此手机号
            $status = $this->_getPlayUserTable()->update(array(
                'phone' => $phone,
                'password' => 0
            ), array('uid' => $uid));
        }

        if ($status) {
            $this->use_auth_code($phone, $code);
            return $this->jsonResponse(array('status' => 1, 'message' => '绑定成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '数据插入失败'));
        }

    }


    public function wapregisterAction()
    {

        Logger::WriteErrorLog('停止访问2'.print_r($_POST,true));
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $code = $this->getPost('code');//验证码
        $phone = $this->getPost('phone');
        $uid = $this->getPost('uid');
        if (!$phone or !$code or !$uid) {
            return $this->jsonResponseError('参数错误');
        }
        //验证码
        if (!$this->check_auth_code($phone, $code)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
        }
        $status = false;
        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if ($user_data) {
            $new = 0;
            //将此微信号绑定到对应手机号用户
            $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid)); //微信授权相关信息
//          $status = $this->_getPlayUserWeiXinTable()->update(array('uid' => $user_data->uid), array('uid' => $uid)); //关联到新的uid
            $status = $this->initWeiXinData($user_data->uid, $weixin_data->open_id, $weixin_data->unionid, $user_data->token);
            $this->setCookie($user_data->uid, $user_data->token, $weixin_data->open_id, $phone); //新数据  // 需要保留token与客户端相同

        } else {
            //注册

            $token = md5(md5($code) . time());
            $new = 1;
            $res = $this->_getPlayUserTable()->update(array(
                'token' => $token,
                'mark_info' => 0,
                'phone' => $phone,
                //'login_type' => 'phone', //wap注册了 login_type 就是weixin_wap 无需更新
                'is_online' => 1,
                'dateline' => time(),
                'status' => 1,
                'password' => '',
//                'city' =>$this->getCity(),
            ), array('uid' => $uid));
            if($res){
                $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid)); //微信授权相关信息
                $status = $this->initWeiXinData($uid, $weixin_data->open_id, $weixin_data->unionid, $token);
                $this->setCookie($uid, $token, $weixin_data->open_id, $phone); //新数据  // 需要保留token与客户端相同
            }
        }

        if ($status) {
            $this->use_auth_code($phone, $code);
            $dtid = (array_key_exists('dtid', $_COOKIE) && $_COOKIE['dtid']) ? $_COOKIE['dtid'] : 0;
            $openid = (array_key_exists('open_id', $_COOKIE) && $_COOKIE['open_id']) ? $_COOKIE['open_id'] : 0;
            $adapter = $this->_getAdapter();
            $adapter->query("update weixin_ditui_log set is_new = ?,phone = ?,registed = ? where dtid = ? and fromid = ?
 and open_id = ? and phone = 0",
                array($new, $phone, 1, $dtid, 'wx', $openid))->count();
            return $this->jsonResponse(array('status' => 1, 'message' => '绑定成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '数据插入失败'));
        }
    }

    /**
     * 地推特殊使用
     * @return \Zend\View\Model\JsonModel
     */
    public function dituiregisterAction()
    {
        $is_wap = $this->getPost('wap');

        if (!$is_wap) {
            if (!$this->pass()) {
                return $this->failRequest();
            }
        }

        $code = $this->getPost('code');//验证码
        $phone = $this->getPost('phone');

        $openid = $this->getPost('openid', 0);
        $did = $this->getPost('did', 0);

        if (!$phone or !$code or !$did) {
            return $this->jsonResponseError('参数错误');
        }
        //验证码
        if (!$this->check_auth_code($phone, $code)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '验证码错误'));
        }
        $s2 = $s3 = $s4 = false;
        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        $adapter = $this->_getAdapter();
        if ($user_data) {
            if ($openid > 0) {//来自微信

            } else {//来自app
                $s2 = $adapter->query("SELECT * from weixin_ditui_log WHERE dtid = ? and phone = ? and fromid = ? ",
                    array($phone, $did, 'app'))->count();

                if (!$s2) {
                    $s3 = $adapter->query("INSERT INTO weixin_ditui_log (`id`,`open_id`,`scene`,`weixin_name`,`concern_num`,`union_id`,`nick_name`,
`is_on`,`concern_time`,`is_new`,`registed`,`dtid`,`phone`,`gifted`,`fromid`,`max`,`per`)
 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array(0, 0, ' ', 1, 0, ' ', 0, time(), 0, 1, $did, $phone, 0, 'app', 0, 0))->count();
                }

            }
            $token = md5(md5($code) . time());

            $this->setCookie($user_data->uid, $token, $openid, $phone); //新数据  // 需要保留token与客户端相同
            $uid = $user_data->uid;
            $isold = 0;
        } else {
            //注册
            $token = md5(md5($code) . time());
            $isold = 1;
            $res = $this->_getPlayUserTable()->insert(array(
                'username' => $phone,
                'token' => $token,
                'mark_info' => 0,
                'phone' => $phone,
                'login_type' => 'phone',
                'is_online' => 1,
                'device_type' => '',
                'dateline' => time(),
                'status' => 1,
                'password' => '',
                'city' => $this->getCity(),
            ));
            if ($res) {
                $user_id = $this->_getPlayUserTable()->getlastInsertValue();
                $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $user_id)); //微信授权相关信息
                if ($weixin_data) {
                    $status = $this->initWeiXinData($user_id, $weixin_data->open_id, $weixin_data->unionid, $token);
                    $this->setCookie($user_id, $token, $weixin_data->open_id, $phone); //新数据  // 需要保留token与客户端相同
                }
            }
            $uid = $user_id;
            //wft
            $log['is_new'] = 1;
            $log['registed'] = 1;
            $log['dtid'] = $did;

            //weixin
            if ($this->is_weixin()) {
            } elseif ($this->is_wft()) {
                $s2 = $adapter->query("SELECT * from weixin_ditui_log WHERE dtid = ? and phone = ? and fromid = ? ",
                    array($phone, $did, 'app'))->count();
                if (!$s2) {
                    $s4 = $adapter->query("INSERT INTO weixin_ditui_log (`id`,`open_id`,`scene`,`weixin_name`,`concern_num`,`union_id`,`nick_name`,`is_on`,`concern_time`,
`is_new`,`registed`,`dtid`,`phone`,`gifted`,`fromid` )
 VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array(0, 0, ' ', 1, 0, ' ', 0, time(), 1, 1, $did, $phone, 0, 'app'))->count();
                }
            }
        }

        if ($s4 || $s3 || $s2) {
            $this->use_auth_code($phone, $code);

            return $this->jsonResponse(array('status' => 1, 'message' => '绑定成功', 'uid' => $uid, 'token' => $token, 'open_id' => $openid, 'phone' => $phone, 'is_old' => $isold));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '数据插入失败'));
        }
    }


    /**
     *
     * 返回登录信息
     * @param $uid
     * @return array
     */
    private function backuserinfo($uid)
    {


        //设置用户为在线
        // $this->_getPlayUserTable()->update(array('username' => $this->username, 'img' => $this->img, 'token' => $token, 'is_online' => 1, 'push_id' => $push_id, 'device_type' => $device), array('mark_info' => $openid, 'login_type' => $login_type));

        // 更改用户的头像
//        $this->_getMdbSocialCircleMsg()->update(array('uid' => (int)$uid), array('$set' => array('img' => $this->getImgUrl($userimg))), array('multiple' => true)); // 用户名称 与 alias
//        $this->_getMdbSocialCircleMsgPost()->update(array('uid' => (int)$uid), array('$set' => array('img' => $this->getImgUrl($userimg))), array('multiple' => true)); // 用户名称 与 alias
//        $this->_getMdbSocialCircleUsers()->update(array('uid' => (int)$uid), array('$set' => array('img' => $this->getImgUrl($userimg))), array('multiple' => true));


        $userdata = $this->_getPlayUserTable()->get(array('uid' => $uid));


        $acc = new Account();

        $acc->initAccount($uid); //初始化账户,生成账户信息

        $res = array(
            'status' => 1,
            'message' => '登录成功',
            'uid' => $userdata->uid,
            'username' => $userdata->username,
            // 'img' => $userdata->img,
            'img' => $userdata->img ? ((strpos($userdata->img, '/') === 0) ? $this->_getConfig()['url'] . $userdata->img : $userdata->img) : '',
            'child_sex' => (int)$userdata->child_sex,
            'child_old' => (int)$userdata->child_old,
            'phone' => $userdata->phone,
            'token' => $userdata->token,
            'merchant' => $this->QueryUserBind($userdata->uid),
            'sex' => (int)$userdata->child_sex,
            'baby_flag' => 0,
            'pay_password' => (int)$acc->getPassword($uid)
        );

        $babyData = $this->_getPlayUserBabyTable()->fetchAll(array('uid' => $userdata->uid));
        if ($babyData->count()) {
            foreach ($babyData as $baby) {
                $res['baby'][] = array(
                    'name' => $baby->baby_name,
                    'sex' => $baby->baby_sex,
                    'birth' => $baby->baby_birth,
                    'id' => $baby->id,
                );
            }
            $res['baby_flag'] = 1;
        }

        return $res;
    }

    private function initWeiXinData($uid, $openid, $unionid, $token)
    {


        $log = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid, 'login_type' => 'weixin_sdk'));

        if (!$log) {
            return $this->_getPlayUserWeiXinTable()->insert(array(
                'uid' => $uid,
                'open_id' => $openid,
                'unionid' => $unionid,
                'access_token_wap' => $token,
                'refresh_token_wap' => '',
                'login_type' => 'weixin_sdk', //微信授权表改为通用授权表
            ));

        } else {
            return true;
        }
    }


    public function setCookie($uid, $token, $openid, $phone)
    {
        $_COOKIE['uid'] = $uid;
        $_COOKIE['open_id'] = $openid;
        $untime = time() + 3600 * 24 * 17;  //失效时间
        setcookie('uid', $uid, $untime, '/');
        setcookie('token', $token, $untime, '/');
        setcookie('open_id', $openid, $untime, '/');
        setcookie('phone', $phone, $untime, '/');
    }

    //获取验证码
    public function getcodeAction()
    {
        $is_wap = $this->getPost('wap');

        if (!$is_wap) {
            if (!$this->pass()) {
                return $this->failRequest();
            }
        }
        $check_phone = 0; //$this->getParams('check_phone', 0);
        $phone = $this->getParams('phone') == '' ? trim($this->getPost('phone')) : $this->getParams('phone');

        if (!is_numeric($phone)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号格式错误'));
        }

        if (!$phone) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号不能为空'));
        }

        if (strlen($phone) != 11) {
            return $this->jsonResponse(array('status' => 0, 'message' => '手机号长度不正确'));
        }

        if ($check_phone) {
            $msg = $this->CheckDeyiPhone($phone);
            if ($msg) {
                return $this->jsonResponse(array('status' => 0, 'message' => $msg));
            }
        }

        // 避免同一用户并发
        if (RedCache::get('send' . $phone)) {
            //todo 间隔时间过短
            return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高，请稍后再试'));
        } else {
            RedCache::set('send' . $phone, time(), 2);
        }


        //todo 一天内 只允许5条,每条相隔1分钟
        $time = time() - 43200;
        $data = $this->_getPlayAuthCodeTable()->fetchAll(array('phone' => $phone, "time>{$time}"), array('id' => 'desc'), 5)->toArray();

        if (!empty($data) and (time() - $data[0]['time']) < 59) {
            return $this->jsonResponse(array('status' => 0, 'message' => '发送频率过高'));
        }
        if (count($data) == 15) {
            return $this->jsonResponse(array('status' => 0, 'message' => '超过每日短信限制'));
        }

        //发送验证码
        $code = SendMessage::Send5($phone,$this->getCity());
        if (!$code) {
            return $this->jsonResponse(array('status' => 0, 'message' => SendMessage::$error));
        } else {
            $this->_getPlayAuthCodeTable()->insert(array('phone' => $phone, 'time' => time(), 'code' => $code, 'status' => 1));
            return $this->jsonResponse(array('status' => 1, 'message' => '验证码发送成功'));
        }

    }


    //退出账号
    public function outAction()
    {
        if (!$this->pass(false)) {
            return $this->failRequest();
        }


        $uid = $this->getParams('uid');
        $token = $this->getParams('token');


        if (!$uid) {
            return $this->jsonResponseError('参数错误');
        }

        $this->_getPlayUserTable()->update(array('is_online' => 0), array('uid' => $uid));
        return $this->jsonResponse(array('status' => 1, 'message' => '退出成功'));


    }


    //查询绑定的商家或组织者
    protected function QueryUserBind($uid)
    {
        $data = array(
            'shop_id' => 0,
            'shop_name' => '',
            'organizer_id' => 0,
            'organizer_name' => ''
        );
        $organizer_res = $this->_getPlayOrganizerTable()->get(array('bind_uid' => $uid));
        if ($organizer_res) {
            $data['organizer_id'] = $organizer_res->id;
            $data['organizer_name'] = $organizer_res->name;
        }
        $shop_res = $this->_getPlayShopTable()->get(array('bind_uid' => $uid));
        if ($shop_res) {
            $data['shop_id'] = $shop_res->shop_id;
            $data['shop_name'] = $shop_res->shop_name;
        }
        return $data;

    }

    protected function check_qq_login($token, $openid, $appid)
    {
        $output = $this->getCurl("https://openmobile.qq.com/user/get_simple_userinfo?access_token={$token}&openid={$openid}&oauth_consumer_key={$appid}");
        if (!$output) {
            return false;
        }
        $data = json_decode($output);
        if ($data->nickname) {
            $this->username = $data->nickname;
            $this->img = $data->figureurl_qq_2;
            return true;
        } else {
            $this->error .= $output;
            return false;
        }
    }

    protected function check_weixin_login($token, $markid)
    {
        $output = $this->getCurl("https://api.weixin.qq.com/sns/userinfo?access_token={$token}&openid={$markid}");
        if (!$output) {
            return false;
        }
        $data = json_decode($output);
        if ($data->nickname) {
            $this->username = $data->nickname;
            $this->img = $data->headimgurl;
            $this->unionid = $data->unionid;
            return $data;
        } else {
            $this->error .= $output;
            return false;
        }

    }

    protected function check_sinaweibo_login($token, $markid)
    {
        $output = $this->getCurl("https://api.weibo.com/2/users/show.json?uid={$markid}&access_token={$token}");
        if (!$output) {
            return false;
        }
        $data = json_decode($output);
        if ($data->name) {
            $this->username = $data->name;
            $this->img = $data->profile_image_url;
            return true;
        } else {
            $this->error .= $output;
            return false;
        }
    }


    protected function CheckDeyiUser($name, $password)
    {
        $params = array('time' => time(), 'name' => $name, 'password' => $password);
        $params['check_code'] = $this->getCheckCode($params);
        $output = $this->getCurl($this->DeyiApiUrl . "/eventshop/login/index?" . http_build_query($params));
        if (!$output) {
            return array('status' => 0, 'message' => $this->error);
        }
        $data = json_decode($output, true);
        return $data;
    }

    protected function getCurl($url)
    {


        /* $ch = curl_init();
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
         curl_setopt($ch, CURLOPT_TIMEOUT, 40);
         curl_setopt($ch, CURLOPT_HEADER, 0);
         $output = curl_exec($ch);
         $HTTP_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
         if ($HTTP_code !== 200) {
             $this->error .= "请求异常,HTTP状态码为:{$HTTP_code}";
             return false;
         }*/

        $output = Request::get($url);
        if ($output) {
            return $output;
        } else {
            $this->error .= "第三方服务器无返回";
            return false;
        }


    }


    protected function CheckDeyiPhone($phone)
    {
        $is_phone = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if ($is_phone) {
            return "该手机已经绑定用户名为{$is_phone->username}的账号";
        }

        return false;
    }


    protected function getCheckCode($params)
    {
        ksort($params);
        foreach ($params as $k => $v) {
            $params[$k] = (string)$v;
        }
        return hash_hmac('sha1', json_encode($params, JSON_UNESCAPED_UNICODE), $this->secret);
    }

    private function check_auth_code($phone, $code)
    {
        $data = $this->_getPlayAuthCodeTable()->get(array('phone' => $phone, 'code' => $code, 'status' => 1));

        // if (!empty($data) and (time() - $data[0]['time']) < 300) {
        if ($data) {//todo 临时
            return true;
        }
        return false;
    }

    private function use_auth_code($phone, $code)
    {
        return $this->_getPlayAuthCodeTable()->update(array('status' => 0), array('phone' => $phone, 'code' => $code, 'status' => 1));
    }

    private function check_password($password)
    {

        $len = mb_strlen($password);
        if ($len > 18 or $len < 6) {
            return false;
        }
        return true;
    }

    //欢迎注册的消息
    private function sendMes($uid, $type = 4)
    {
        $title = '等的就是你！';
        $message = '亲爱的小玩家，恭喜你终于找到大部队了！每周三10:00记得来“一元秒杀”，看看“嗨周末”解决周末遛娃难题，还有不定期放送的“非玩不可”聚划算商品……现在就开启你超乎想象的玩翻天之旅吧！';
        $this->_getPlayUserMessageTable()->insert(array('uid' => $uid, 'type' => $type, 'title' => $title, 'deadline' => time(), 'message' => $message));
    }

}
