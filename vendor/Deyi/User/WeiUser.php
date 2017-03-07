<?php
namespace Deyi\User;

use Application\Module;
use Deyi\BaseController;
use Deyi\WeiXinFun;
use Zend\Db\Sql\Predicate\Expression;
use Deyi\Account\Account;
use Deyi\BaiduLocation;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CityCache;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\GetCacheData\UserCache;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\OrderAction\OrderPay;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Deyi\WeiXinPay\WeiXinPayFun;
use Deyi\WriteLog;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\Mcrypt;

class WeiUser
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public function userInit()
    {


        $weixin = new WeiXinFun($this->getwxConfig());

        //检查当前cookie是否对应当前服务号
        if($_COOKIE['open_id']){
            $res=$this->_getPlayUserWeiXinTable()->get(array('appid'=>$weixin->getappid(),'open_id'=>$_COOKIE['open_id']));
            if(!$res){ //不属于此服务号
                $untime=time()-3600;
                setcookie('uid', 0,$untime , '/','.wanfantian.com');
                setcookie('token', 0, $untime, '/','.wanfantian.com');
                setcookie('open_id', 0, $untime, '/','.wanfantian.com');
                setcookie('phone', 0, $untime, '/','.wanfantian.com');
            }
        }

        if (!$this->checkWeiXinUser()) {
            if (isset($_GET['code'])) {
                //todo 封装  存储相关信息，获取用户信息，生成cookie
                $accessTokenData = $weixin->getUserAccessToken($_GET['code']);

                if (isset($accessTokenData->access_token)) {
                    $token = md5(time() . $accessTokenData->access_token);
                    //先查询用户是否存在
                    $user_data = false;
                    if (!$accessTokenData->unionid) {
                        $accessTokenData->unionid = -1;
                    }
                    $user = $this->_getPlayUserWeiXinTable()->getUserInfo("play_user_weixin.open_id='{$accessTokenData->openid}' or play_user_weixin.unionid='{$accessTokenData->unionid}'");

                    if ($user) {
                        $user_data = $this->_getPlayUserTable()->get(array('uid' => $user->uid));
                    }
                    if ($user && $user_data) {
                        //初始化当前新微信号数据
                        $weixin = $this->_getPlayUserWeiXinTable()->get(array('open_id' => $accessTokenData->openid));
                        if (!$weixin) {
                            $this->_getPlayUserWeiXinTable()->insert(array(
                                'uid' => $user->uid,
                                'appid' => $this->getwxConfig()['appid'],
                                'open_id' => $accessTokenData->openid,
                                'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                                'access_token_wap' => $accessTokenData->access_token,
                                'refresh_token_wap' => $accessTokenData->refresh_token,
                                'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                            ));
                        }

                        $this->setCookie($user->uid, $user->token, $accessTokenData->openid, $user_data->phone);
                        return true;
                    } else {
                        if ($accessTokenData->scope == 'snsapi_userinfo') {
                            $userInfo = $weixin->getUserInfo($accessTokenData->access_token);

                            if (!$userInfo) {
                                //todo 错误处理机制
                                WriteLog::WriteLog('获取userInfo错误:' . print_r($userInfo, true));
                                return false;
                            }
                            if($userInfo->city){
                                $_SERVER['HTTP_CITY']=urlencode($userInfo->city);
                            }
                            $username = $userInfo->nickname;
                            $img = $userInfo->headimgurl;

                        } else {
                            $username = 'WeiXin' . time();
                            $img = '';
                        }

                        $this->_getPlayUserTable()->insert(array(
                            'username' => $username ? $username : '　',//用户名不能为空的BUG
                            'password' => '',
                            'token' => $token,
                            'mark_info' => 0,
                            'login_type' => 'weixin_wap',
                            'is_online' => 1,
                            'device_type' => '',
                            'dateline' => time(),
                            'status' => 1,
                            'img' => $img,
                            'city'=>$this->getCity()
                        ));

                        $uid = $this->_getPlayUserTable()->getlastInsertValue();
                        $status = $this->_getPlayUserWeiXinTable()->insert(array(
                            'uid' => $uid,
                            'appid' => $this->getwxConfig()['appid'],
                            'open_id' => $accessTokenData->openid,
                            'unionid' => isset($accessTokenData->unionid) ? $accessTokenData->unionid : '',
                            'access_token_wap' => $accessTokenData->access_token,
                            'refresh_token_wap' => $accessTokenData->refresh_token,
                            'login_type' => 'weixin_wap', //微信授权表改为通用授权表
                        ));

                        $this->setCookie($uid, $token, $accessTokenData->openid);

                        if (!$status) {
                            return false;
                        } else {
                            return true;
                        }

                    }
                } else {
                    //todo 错误处理机制
                    WriteLog::WriteLog('获取userAccessToken错误:' . print_r($accessTokenData, true));
                    return false;
                }
            } else {
                //todo 如果用户点了拒绝
                return false;
            }
        } else {
            return true;
        }
    }

}