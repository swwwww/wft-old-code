<?php

namespace Web\Controller;

use Application\Model\CashCouponTable;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\GetCacheData\CouponCache;
use Deyi\GetCacheData\GoodCache;
use Deyi\Invite\Invite;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\WeiXinFun;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class GroundController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    public function __construct()
    {
        if($_GET['debug']){
            setcookie('debug',1,time()+600,'/');
        }

//设置请求第三方时mysql断开连接的问题
        ini_set('mysql.connect_timeout', 60);
        ini_set('default_socket_timeout', 60);
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

    //初始化用户 生成用户 生成验证信息
    public function userInit(WeiXinFun $weixin)
    {
        if (!$this->checkWeiXinUser()) {
            if (isset($_GET['code'])) {
                //todo 封装  存储相关信息，获取用户信息，生成cookie
                $accessTokenData = $weixin->getUserAccessToken($_GET['code']);
                //TODO 上线删
                if((int)$_COOKIE['debug']){
                    $test = explode('test', $this->_getConfig()['url']);
                    if (count($test) === 2) {
//                        $accessTokenData->openid = md5(time());
//                        $accessTokenData->unionid = md5(time()+9);
                    }
                }

                if (isset($accessTokenData->access_token)) {
                    $token = md5(time() . $accessTokenData->access_token);
                    //先查询用户是否存在
                    $user_data = false;
                    if (!$accessTokenData->unionid) {
                        $accessTokenData->unionid = -1;
                    }
                    $user = $this->_getPlayUserWeiXinTable()->getUserInfo("play_user_weixin.open_id='{$accessTokenData->openid}'
                    or play_user_weixin.unionid='{$accessTokenData->unionid}'");

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

    public function setCookie($uid, $token, $openid, $phone = '')
    {
        $_COOKIE['uid'] = $uid;
        $_COOKIE['open_id'] = $openid;
        $_COOKIE['phone'] = $phone ?: 0;
        $_COOKIE['token'] = $token ?: '';
        $untime = time() + 3600 * 24 * 17;  //失效时间
        setcookie('uid', $uid, $untime, '/');
        setcookie('token', $token, $untime, '/');
        setcookie('open_id', $openid, $untime, '/');
        setcookie('phone', $phone, $untime, '/');
    }

    public function indexAction()
    {
        $dtid = (int)$this->getQuery('dtid', 0);
        if ($dtid) {
            $untime = time() + 3600 * 24 * 1;  //失效时间
            setcookie('dtid', $dtid, $untime, '/');
        }

        $opt = $this->getOptions();
        if ($opt) {
            $untime = time() + 3600 * 24 * 1;  //失效时间

            $_COOKIE['city'] = $opt->city;
            setcookie('city', $_COOKIE['city'], $untime, '/');
        }
        if ($this->is_weixin()) {

            $back_url = $this->_getConfig()['url'] . '/web/ground?dtid=' . $dtid;

            $weixin = new WeiXinFun($this->getwxConfig());

            if ($this->userInit($weixin)) {
                //记录扫码openid
                $this->dituilog();

                $oui = $weixin->getOdinaryUserInfo($_COOKIE['open_id']);
                $openid = array_key_exists('open_id', $_COOKIE) ? $_COOKIE['open_id'] : -1;
                if ($oui->errcode || !$oui->subscribe) {
                    $url = $this->_getConfig()['url'] . '/web/ground/qrcode?dtid=' . $dtid;
                    header('Location: ' . $url);
                    exit;
                } else {
                    setcookie('is_on', 1, $untime, '/');
                    if (!$_COOKIE['is_on']) {
                        $this->_getWeixinDituiLogTable()->update(['is_on' => 2],
                            ['fromid' => 'wx', 'is_on' => 0, 'open_id' => $openid, 'dtid' => $dtid]);
                    }
                }

                $untime = time() + 3600 * 24 * 1;  //失效时间
                setcookie('nkname', $oui->nickname, $untime, '/');

                $status = $this->checkPhone();//用来检查关联微信号是否绑定了手机号，无法检测没有使用微信登陆的手机用户

                if ($status) {
                    $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : -1;
                    $l = $this->_getWeixinDituiLogTable()->fetchLimit(0, 1, [],
                        ['dtid' => $dtid, 'open_id' => $openid, 'fromid' => 'wx'])->current();
                    $log['phone'] = $phone;

                    if ($l and (int)$l->phone === 0 and $phone) {
                        $log['is_new'] = 0;
                        $log['registed'] = 1;
                        $this->_getWeixinDituiLogTable()->update($log,
                            ['fromid' => 'wx', 'open_id' => $openid, 'dtid' => $dtid]);
                    }

                    $url = $this->_getConfig()['url'] . '/web/ground/confirm?dtid=' . $dtid;
                    header('Location: ' . $url);
                    exit;
                } else {
                    $backUrl = $this->_getConfig()['url'] . '/web/ground/confirm?dtid=' . $dtid;
                    $url = $this->_getConfig()['url'] . "/web/wappay/register?uid={$_COOKIE['uid']}&tourl=" . urlencode($backUrl);
                    header("Location: $url");
                    exit;
                }

            } else {
                $untime = time() - 3600;  //失效时间
                setcookie('uid', 0, $untime, '/');
                setcookie('token', 0, $untime, '/');
                setcookie('cid', 0, $untime, '/');
                setcookie('open_id', 0, $untime, '/');
                setcookie('dtid', 0, $untime, '/');
                setcookie('phone', 0, $untime, '/');

                //todo 授权失败
                $toUrl = $weixin->getAuthorUrl($back_url, 'snsapi_userinfo');
                header("Location: $toUrl");
                exit;
            }

        } elseif ($this->is_wft()) {
            $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : 0;

            if (!$phone) {
                $url = $this->_getConfig()['url'] . '/web/ground/register?dtid=' . $dtid;
                header('Location: ' . $url);
                exit;
            }

            $url = $this->_getConfig()['url'] . '/web/ground/confirm?t=3&dtid=' . $dtid;
            header('Location: ' . $url);
            exit;
        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

    }

    private function getDtid()
    {
        $dtid = (int)$this->getQuery('dtid', 0);
        if (!$dtid) {
            $dtid = (array_key_exists('dtid', $_COOKIE) && (int)$_COOKIE['dtid']) ? $_COOKIE['dtid'] : 0;
        }

        $ground = RedCache::fromCacheData('D:dtop:' . $dtid, function () use ($dtid) {
            $data = $this->_getPlayGroundTable()->get(['id' => $dtid]);

            return $data;
        }, 24 * 3600, true);

        $ground = (Object)$ground;

        if (!@$ground->createtime) {
            $ground = $this->_getPlayGroundTable()->get(['id' => $dtid]);
        }

        if ($ground && $ground->createtime) {
            $untime = time() + 3600 * 24 * 1;  //失效时间
            $_COOKIE['dtid'] = $dtid;
            setcookie('dtid', $dtid, $untime, '/');

            return $dtid;
        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
    }

    public function qrcodeAction()
    {
        $view = new viewModel(array());
        $view->setTerminal(true);

        return $view;
    }

    public function checkPhone()
    {
        if (!isset($_COOKIE['phone']) || !$_COOKIE['phone']) {
            //临时查询用户是否已绑定手机号

            $user_data = $this->_getPlayUserTable()->get(array('uid' => (int)$_COOKIE['uid']));

            if ($user_data->phone) {
                $untime = time() + 3600 * 24 * 1;  //失效时间
                $_COOKIE['phone'] = $user_data->phone;
                setcookie('phone', $user_data->phone, $untime, '/');

                return true;
            } else {
                return false;
            }

        } else {
            $user_data = $this->_getPlayUserTable()->get(array('phone' => (int)$_COOKIE['phone']));
            if ($user_data) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 地推扫码记录
     */
    private function dituilog()
    {
        $openid = array_key_exists('open_id', $_COOKIE) ? $_COOKIE['open_id'] : false;
        $dtid = $this->getDtid();
        $db = $this->_getAdapter();
        $res = $db->query('SELECT * FROM play_user left join play_user_weixin on play_user.uid = play_user_weixin.uid and play_user_weixin.open_id = ? limit 1',
            array($openid))->current();

        if ($this->is_weixin()) {

            $l = $this->_getWeixinDituiLogTable()->fetchLimit(0, 1, [],
                ['dtid' => $dtid, 'open_id' => $openid, 'fromid' => 'wx'])->current();

            if ($l) {
                return false;
            }
            $log['open_id'] = $openid;
            $log['fromid'] = 'wx';
        } elseif ($this->is_wft()) {
            $l = $this->_getWeixinDituiLogTable()->fetchLimit(0, 1, [],
                ['dtid' => $dtid, 'fromid' => 'app'])->current();
            if ($l) {
                return false;
            }
            $log['open_id'] = 0;
            $log['fromid'] = 'app';
        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $log['weixin_name'] = '玩翻天';
        $log['concern_num'] = 1;
        $log['phone'] = 0;
        $log['union_id'] = $res->unionid ?: 0;
        $log['nick_name'] = $res->nickname ?: ' ';
        $log['is_on'] = 0;
        $log['concern_time'] = time();
        $log['is_new'] = 1;
        $log['registed'] = 0;
        $log['dtid'] = $this->getDtid();
        $this->_getWeixinDituiLogTable()->insert($log);

        $_COOKIE['open_id'] = $openid;
        $untime = time() + 3600 * 24 * 1;  //失效时间
        setcookie('open_id', $openid, $untime, '/');
    }

    /**
     * 领奖记录
     */
    public function dogiftAction()
    {
        $openid = array_key_exists('open_id', $_COOKIE) ? $_COOKIE['open_id'] : false;
        $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : false;

        $action = __FUNCTION__;
        $this->doRoute($action);

        $log['gifted'] = 1;

        if ($this->is_weixin()) {
            $weixin = new WeiXinFun($this->getwxConfig());
            $oui = $weixin->getOdinaryUserInfo($openid);
            if ($oui->errcode || !$oui->subscribe) {
                $url = $this->_getConfig()['url'] . '/web/ground/qrcode?t=5&dtid=' . $this->getDtid();
                header('Location: ' . $url);
                exit;
            }
            //TODO 添加关注字段
            $log['is_on'] = new Expression('is_on + 1');
            $s = $this->_getWeixinDituiLogTable()->update($log,
                ['fromid' => 'wx', 'open_id' => $openid, 'gifted' => 0, 'phone' => $phone, 'dtid' => $this->getDtid()]);
        } elseif ($this->is_wft()) {
            $s = $this->_getWeixinDituiLogTable()->update($log,
                ['fromid' => 'app', 'phone' => $phone, 'gifted' => 0, 'dtid' => $this->getDtid()]);

        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        if ($s) {
            $this->getGifts();
        }

        $url = $this->_getConfig()['url'] . '/web/ground/new?t=6&dtid=' . $this->getDtid();
        header('Location: ' . $url);
        exit;

    }

    private function getOptions()
    {
        $did = $this->getDtid();

        $ground = RedCache::fromCacheData('D:dtop:' . $did, function () use ($did) {
            $data = $this->_getPlayGroundTable()->get(['id' => $did]);

            return $data;
        }, 24 * 3600, true);

        if (!$ground) {
            return $this->_getPlayGroundTable()->get(['id' => $did]);
        }

        $ground = (Object)$ground;

        if (!@$ground->createtime) {
            return false;
        }

        return $ground;
    }

    //请工作人员确认领奖
    public function confirmAction()
    {
        $dtop = $this->getOptions();

        if (!$dtop || $dtop->etime < time() || $dtop->stime > time() || (int)$dtop->status === 0) {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $oldhas = $dtop->old;

        $action = __FUNCTION__;
        $this->doRoute($action);

        $old = $this->isOld();


        if ($this->is_weixin() and !$this->getDtid()) {
            $url = $this->_getConfig()['url'] . '/web/wappay/nindex';
            header('Location: ' . $url);
            exit;
        }

        //判断是否老用户有奖
        if (!$oldhas && $old) {
            $url = $this->_getConfig()['url'] . '/web/ground/result?t=7&dtid=' . $this->getDtid();
            header('Location: ' . $url);
            exit;
        }

        if ($this->is_weixin() and !array_key_exists('is_on', $_COOKIE)) {
            $weixin = new WeiXinFun($this->getwxConfig());
            $oui = $weixin->getOdinaryUserInfo($_COOKIE['open_id']);
            if ($oui->errcode || !$oui->subscribe) {
                $url = $this->_getConfig()['url'] . '/web/ground/qrcode?t=5&dtid=' . $this->getDtid();
                header('Location: ' . $url);
                exit;
            }
        }

        $view = new viewModel(array());
        $view->setTerminal(true);

        return $view;

    }

    private function getUidByPhone()
    {
        $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : -1;

        $user = $this->_getPlayUserTable()->get(['phone' => $phone]);

        if ($user->uid) {
            $uid = $user->uid;

            return $uid;
        } else {
            $url = $this->_getConfig()['url'] . '/web/ground/register?t=8&dtid=' . $this->getDtid();
            header('Location: ' . $url);
            exit;
        }
    }

    private function getGifts()
    {
        $openid = array_key_exists('open_id', $_COOKIE) ? $_COOKIE['open_id'] : false;
        $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : false;
        $uid = array_key_exists('uid', $_COOKIE) ? $_COOKIE['uid'] : 0;
        $dtid = $this->getDtid();

        if (!$phone and !$openid and !$uid) {
            return false;
        }

        $city = $this->getCity();

        //给予奖励
        $award = $this->getOptions();
        if (!$award) {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $t = $award->title;
        if ($award) {
            $award = json_decode((string)$award->options);
        }

        $old = $this->isOld();

        if ($old) {
            //现金券
            $cc = null;
            if ($award->cashcoupon_old) {
                $cid = explode(',', $award->cashcoupon_no_old);

                $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
                $coupon = new Coupon();
                $status = $coupon->addCashcoupon($uid, $cc->id, 0, 12, 0, $t, $city);

                if ($status) {
                    $untime = time() + 3600 * 24 * 1;  //失效时间
                    $_COOKIE['cid'] = $cc->id;
                    setcookie('cid', $cc->id, $untime, '/');
                }
            }

            //返利
            if ($award->backcash_old) {
                $log['per'] = (float)$award->backcash_value_old;
                $log['max'] = (float)$award->backcash_max_old;

                if ($this->is_weixin()) {
                    $status = $this->_getWeixinDituiLogTable()->update($log,
                        ['fromid' => 'wx', 'open_id' => $openid, 'phone' => $phone, 'dtid' => $dtid]);
                } elseif ($this->is_wft()) {
                    $status = $this->_getWeixinDituiLogTable()->update($log,
                        ['fromid' => 'app', 'phone' => $phone, 'dtid' => $dtid]);
                } else {
                    $status = 0;
                }
            }

            //资格券
            if ($award->quailty_old) {
                $q = $award->quailty_num_old;
                $coupon = new Coupon();
                for ($i = 0; $i < $q; $i++) {
                    $coupon->addQualify($uid, 3, $this->getAdminCity(), 3, 0, 0, 0);
                }
            }

            //积分
            $i = 0;
            if ($award->integral_old) {
                $i = $award->integral_value_old;
            }
        }

        if (!$old) {
            //现金券
            $cc = null;
            if ($award->cashcoupon) {
                $cid = explode(',', $award->cashcoupon_no);

                $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
                $coupon = new Coupon();
                $status = $coupon->addCashcoupon($uid, $cc->id, 0, 12, 0, $t, $city);

                if ($status) {
                    $untime = time() + 3600 * 24 * 1;  //失效时间
                    $_COOKIE['cid'] = $cc->id;
                    setcookie('cid', $cc->id, $untime, '/');
                }
            }

            //返利
            if ($award->backcash) {
                $log['per'] = (float)$award->backcash_value;
                $log['max'] = (float)$award->backcash_max;

                if ($this->is_weixin()) {
                    $status = $this->_getWeixinDituiLogTable()->update($log,
                        ['fromid' => 'wx', 'open_id' => $openid, 'phone' => $phone, 'dtid' => $dtid]);
                } elseif ($this->is_wft()) {
                    $status = $this->_getWeixinDituiLogTable()->update($log,
                        ['fromid' => 'app', 'phone' => $phone, 'dtid' => $dtid]);
                } else {
                    $status = 0;
                }
            }

            //资格券
            if ($award->quailty) {
                $q = $award->quailty_num;
                $coupon = new Coupon();
                for ($i = 0; $i < $q; $i++) {
                    $coupon->addQualify($uid, 3, $this->getAdminCity(), 3, 0, 0, 0);
                }
            }

            //积分
            $i = 0;
            if ($award->integral) {
                $i = $award->integral_value;
            }
        }

    }

    private function lightold($log)
    {

        $wxl = $apl = 0;
        if ($this->is_weixin()) {
            $wxl = $log;
        } elseif ($this->is_wft()) {
            $apl = $log;
        }

        $old = 0;
        if (($apl && (int)$apl->is_new === 0) || ($wxl && (int)$wxl->is_new === 0)) {
            $old = 1;
        }
        $untime = time() + 3600 * 24 * 1;  //失效时间
        $_COOKIE['old'] = $old;
        setcookie('old', $old, $untime, '/');

        return $old;
    }

    private function isOld()
    {
        $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : -1;
        $dtid = $this->getDtid();
        $wxl = $apl = 0;
        if ($this->is_weixin()) {
            $wxl = $this->_getWeixinDituiLogTable()->fetchLimit(0, 1, [],
                ['dtid' => $dtid, 'phone' => $phone, 'fromid' => 'wx'])->current();
        } elseif ($this->is_wft()) {
            $apl = $this->_getWeixinDituiLogTable()->fetchLimit(0, 1, [],
                ['dtid' => $dtid, 'phone' => $phone, 'fromid' => 'app'])->current();
        }

        $old = 0;
        if (($apl && (int)$apl->is_new === 0) || ($wxl && (int)$wxl->is_new === 0)) {
            $old = 1;
        }
        $untime = time() + 3600 * 24 * 1;  //失效时间
        $_COOKIE['old'] = $old;
        setcookie('old', $old, $untime, '/');

        return $old;
    }

    //新用户获得
    public function newAction()
    {

        $action = __FUNCTION__;
        $this->doRoute($action);

        $jump = 'http://wan.wanfantian.com/app/index.php';
        if ($this->is_weixin()) {
            $weixin = new WeiXinFun($this->getwxConfig());
            $oui = $weixin->getOdinaryUserInfo($_COOKIE['open_id']);
            if ($oui->errcode || !$oui->subscribe) {
                $url = $this->_getConfig()['url'] . '/web/ground/qrcode?t=9&dtid=' . $this->getDtid();
                header('Location: ' . $url);
                exit;
            }
            $jump = '/web/wappay/nindex';
        } elseif ($this->is_wft()) {
            $jump = '/web/wappay/nindex';
        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $this->getUidByPhone();

        $old = $this->isOld();

        //给予奖励
        $award = $this->getOptions();
        if (!$award) {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
        if ($award) {
            $award = json_decode((string)$award->options);
        }

        //现金券
        $cc = null;
        if ($award->cashcoupon) {
            $cid = explode(',', $award->cashcoupon_no);
            $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
            //residue > 0 and end_time > {$time} and status = 1 and is_close = 0"
            if ($cc->residue < 1 || $cc->end_time < time() || $cc->status < 1 || $cc->is_close > 0) {
                $cc = null;
            }
        }

        //返利
        $bcv = $bcm = 0;
        if ($award->backcash) {
            $bcv = $award->backcash_value;
            $bcm = $award->backcash_max;
        }

        //资格券
        $q = 0;
        if ($award->quailty) {
            $q = $award->quailty_num;
        }

        //积分
        $i = 0;
        if ($award->integral) {
            $i = $award->integral_value;
        }

        if ($old) {
            //现金券
            $cc = null;
            if ($award->cashcoupon_old) {
                $cid = explode(',', $award->cashcoupon_no_old);
                $cc = $this->_getCashCouponTable()->get(['id' => $cid[0]]);
                if ($cc->residue < 1 || $cc->end_time < time() || $cc->status < 1 || $cc->is_close > 0) {
                    $cc = null;
                }
            }

            //返利
            $bcv = $bcm = 0;
            if ($award->backcash_old) {
                $bcv = $award->backcash_value_old;
                $bcm = $award->backcash_max_old;
            }

            //资格券
            $q = 0;
            if ($award->quailty_old) {
                $q = $award->quailty_num_old;
            }

            //积分
            $i = 0;
            if ($award->integral_old) {
                $i = $award->integral_value_old;
            }
        }

        $view = new viewModel(array(
            'cc' => $cc,
            'cid' => $cid[0] ?: 0,
            'bcv' => $bcv,
            'bcm' => $bcm,
            'q' => $q,
            'i' => $i,
            'old' => $old,
            'jump' => $jump
        ));

        $view->setTerminal(true);

        return $view;

    }

    //判断是否设置了奖品
    private function haspriaze($opt)
    {
        $opt_arr = (array)json_decode($opt->options);
        if (is_array($opt_arr)) {
            if ($opt_arr['cashcoupon'] || $opt_arr['backcash'] || $opt_arr['quailty'] || $opt_arr['integral'] || $opt->old) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function doRoute($action)
    {
        $phone = (array_key_exists('phone', $_COOKIE) && $_COOKIE['phone']) ? $_COOKIE['phone'] : false;
        $openid = (array_key_exists('open_id', $_COOKIE) && $_COOKIE['open_id']) ? $_COOKIE['open_id'] : false;


        if ($this->is_weixin()) {

            if ((!$openid || !$phone) and $action !== 'indexAction') {
                $url = $this->_getConfig()['url'] . '/web/ground?t=11&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if ($action === 'registerAction') {
                $url = $this->_getConfig()['url'] . '/web/ground?t=10&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            $log = $this->_getWeixinDituiLogTable()->get([
                'open_id' => $openid ?: -1,
                'fromid' => 'wx',
                'dtid' => $this->getDtid()
            ]);

            $getOptions = $this->getOptions();

            if (!$getOptions) {
                header('Location: http://wan.wanfantian.com/app/index.php');
                exit;
            }

            if (!$log || !$log->phone) {
                $url = $this->_getConfig()['url'] . '/web/ground?t=13&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            $old = $this->lightold($log);

            if (!$getOptions->old && $old) {
                $url = $this->_getConfig()['url'] . '/web/ground/result?t=12&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if (!$this->haspriaze($getOptions)) {//如果没有奖品到个人中心
                header('Location: ' . $this->_getConfig()['url'] . '/web/wappay/nindex');
                exit;
            }

            if ($log and $log->phone and !$log->gifted and $action !== 'confirmAction' and $action !== 'dogiftAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/confirm?t=15&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if ($log and (!$log->phone || $log->gifted) and $action === 'dogiftAction') {
                return false;
            }

            if ($log and $log->phone and $log->gifted and $action !== 'newAction' and $action !== 'giftAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/new?t=16&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

        } elseif ($this->is_wft()) {
            if (!$phone and $action !== 'registerAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/register?t=17&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            $log = $this->_getWeixinDituiLogTable()->get([
                'phone' => $phone ?: -1,
                'fromid' => 'app',
                'dtid' => $this->getDtid()
            ]);

            if (!$log and $action !== 'registerAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/register?t=18&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            $getOptions = $this->getOptions();
            if (!$getOptions) {
                header('Location: http://wan.wanfantian.com/app/index.php');
                exit;
            }

            $old = $this->lightold($log);
            if (!$getOptions->old && $old) {
                $url = $this->_getConfig()['url'] . '/web/ground/result?dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if ($log and !$log->phone and $action !== 'registerAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/register?t=19&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if ($log and $log->phone and !$log->gifted and $action !== 'confirmAction' and $action !== 'dogiftAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/confirm?t=15&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }

            if ($log and (!$log->phone || $log->gifted) and $action === 'dogiftAction') {
                return false;
            }

            if ($log and $log->phone and $log->gifted and $action !== 'newAction' and $action !== 'giftAction') {
                $url = $this->_getConfig()['url'] . '/web/ground/new?t=16&dtid=' . $this->getDtid();
                header("Location: $url");
                exit;
            }
        } else {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }
    }

    //绑定手机
    public function registerAction()
    {
        $action = __FUNCTION__;
        $this->doRoute($action);

        if ($this->is_weixin()) {
            $url = $this->_getConfig()['url'] . '/web/ground?t=0&dtid=' . $this->getDtid();
            header("Location: $url");
            exit;
        }

        $view = new viewModel(array(
            'open_id' => 0,
            'did' => $this->getDtid(),
        ));

        $view->setTerminal(true);

        return $view;

    }

    private function canbuy($cid,$city){
        $coupon = $this->_getCashCouponTable()->get(['id' => $cid]);
        $page = (int)$this->getPost('page', 1);
        $limit = (int)$this->getParams('page_num', 5);
        $page = ($page > 1) ? $page : 1;

        if (!$coupon) {
            $url = $this->_getConfig()['url'] . '/web/ground/empty?msg=现金券不存在';
            header("Location: $url");
            exit;
        }

        $ccu = $this->_getCashCouponUserTable()->get([
            'uid' => $_COOKIE['uid'],
            'pay_time' => 0,
            'cid' => $cid
        ]);

        if (!$ccu) {
            $url = $this->_getConfig()['url'] . '/web/ground/empty?msg=没有领到现金券，换个姿势试下吧';
            header("Location: $url");
            exit;
        } elseif ($ccu->use_etime < time()) {
            $url = $this->_getConfig()['url'] . '/web/ground/empty?msg=现金券过期了';
            header("Location: $url");
            exit;
        }

        //城市范围
        if (false !== $coupon && !$coupon->is_main) {//部分城市, 如果用户不在券城市范围类，将没有票券
            $citys = $this->_getCashCouponCityTable()->fetchAll(array('cid' => $cid))->toArray();
            $cids = [];
            if ($citys) {
                foreach ($citys as $c) {
                    $cids[] = $c['city'];
                }
            } else {
                return $this->jsonResponse([]);
            }

            if (!in_array($city, $cids, true)) {
                return $this->jsonResponse([]);
            }
        }

        //商品范围
        if (1 === (int)$coupon->range) {//部分商品
            $goods = $this->_getCashCouponGoodTable()->fetchAll(array(
                'cid' => $cid,
                'object_type' => $coupon->range
            ))->toArray();

            if ($goods) {
                $ids = [];
                foreach ($goods as $g) {
                    $ids[] = $g['object_id'];
                }
            }

            $goods = $this->_getPlayOrganizerGameTable()->fetchLimit((($page - 1) * $limit), $limit, [],
                [
                    'id' => $ids,
                    'city' => $city,
                    'status > ?' => 0,
                    'large_price >= ?' => $coupon->price,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time()
                ], ['hot_number' => 'DESC'])->toArray();

            $count = $this->_getPlayOrganizerGameTable()->fetchAll(
                [
                    'id' => $ids,
                    'city' => $city,
                    'status > ?' => 0,
                    'large_price >= ?' => $coupon->price,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time()
                ])->count();

        } elseif (2 === (int)$coupon->range) {//部分类别
            $types = $this->_getCashCouponGoodTable()->fetchAll(array('cid' => $cid, 'object_type' => $coupon->range));
            foreach ($types as $t) {
                $typeids[] = $t->object_id;
            }
            $good_ids = $this->_getPlayLabelLinkerTable()->fetchLimit(0, 100, ['object_id'],
                array('lid' => $typeids, 'link_type' => 2))->toArray();

            $ids = [];
            if ($good_ids) {
                foreach ($good_ids as $gi) {
                    $ids[] = $gi['object_id'];
                }
            }
            $goods = $this->_getPlayOrganizerGameTable()->fetchLimit((($page - 1) * $limit), $limit, [],
                [
                    'id' => $ids,
                    'city' => $city,
                    'status > ?' => 0,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time(),
                    'large_price >= ?' => $coupon->price
                ], ['hot_number' => 'DESC'])->toArray();

            $count = $this->_getPlayOrganizerGameTable()->fetchAll(
                [
                    'id' => $ids,
                    'city' => $city,
                    'status > ?' => 0,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time(),
                    'large_price >= ?' => $coupon->price
                ])->count();

        } else {//全场通用
            $goods = $this->_getPlayOrganizerGameTable()->fetchLimit((($page - 1) * $limit), $limit, [],
                [
                    'city' => $city,
                    'status > ?' => 0,
                    'large_price >= ?' => $coupon->price,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time()
                ],
                ['hot_number' => 'DESC'])->toArray();
            $count = $this->_getPlayOrganizerGameTable()->fetchAll(
                [
                    'city' => $city,
                    'status > ?' => 0,
                    'large_price >= ?' => $coupon->price,
                    'start_time <= ?' => time(),
                    'end_time >= ?' => time()
                ]
            )->count();
        }
        return ['coupon'=>$coupon,'goods'=>$goods,'count'=>$count];
    }

    //礼品列表
    public function giftAction()
    {
        if ($this->is_wft()) {
            $url = $this->_getConfig()['url'] . '/web/ground/new?t=0&dtid=' . $this->getDtid();
            header("Location: $url");
            exit;
        } elseif (!$this->is_weixin()) {
            header('Location: http://wan.wanfantian.com/app/index.php');
            exit;
        }

        $action = __FUNCTION__;
        $this->doRoute($action);

        $city = $this->getCity();
        $phone = array_key_exists('phone', $_COOKIE) ? $_COOKIE['phone'] : -1;

        $user = $this->_getPlayUserTable()->get(['phone' => $phone]);

        $nkname = (array_key_exists('nkname', $_COOKIE) && $_COOKIE['nkname']) ? $_COOKIE['nkname'] : $phone;

        if (!$user) {
            $url = $this->_getConfig()['url'] . '/web/ground?dtid=' . $this->getDtid();
            header('Location: ' . $url);
            exit;
        } else {
            $username = $nkname ?: $user->username;
        }
        $cid = (int)$this->getQuery('cid', 0);
        $canbuy = $this->canbuy($cid,$city);
        $goods = $canbuy['goods'];
        $coupon = $canbuy['coupon'];
        $count = $canbuy['count'];

        $data = [];
        if (!$goods) {
            return $this->jsonResponse([]);
        }
        $infos = [];

        foreach ($goods as $g) {
            $res = [];
            $res['id'] = $g['id'];
            $res['cover'] = $this->getImgUrl($g['cover']);
            $res['title'] = $g['title'];
            $res['price'] = $g['low_price'];
            $res['has'] = ($g['ticket_num'] - $g['buy_num']) ?: 0;
            $res['refund'] = ($g['refund_time'] > time()) ? '支持退款' : '不支持退款';
            $res['editor_talk'] = $g['editor_talk'];
            $res['tag'] = GoodCache::getGameTags($g['id'], ($g['post_award'] == 2));
            $res['info'] = CouponCache::getGameInfos($g['id']);
            $res['gurl'] = '/web/organizer/shops?id=' . $g['id'];
            if ($res['info']) {
                foreach ($res['info'] as $k => $info) {
                    $infos[] = $info['id'];
                    $res['info'][$k]['infourl'] = "/web/wappay/ordersubmit?id=" . $g['id'] . "&tid=" . $info['id'] . "&g_buy=0";
                }
            }
            $data[] = $res;
        }

        $welfare = [];
        if ($infos) {
            $welfare = $this->_getPlayWelfareTable()->fetchLimit(0, 1000, [],
                ['object_type' => 2, 'good_info_id' => $infos, 'give_time' => 1])->toArray();
        }
        $welfares = [];
        if ($welfare) {
            foreach ($welfare as $w) {
                $welfares[$w['good_info_id']] = $w;
            }
        }
        $wap = (int)$this->getPost('wap', 0);
        if ($wap) {
            $temp = $data;
            foreach ($temp as $k => $t) {
                if ($t && is_array($t['info'])) {
                    foreach ($t['info'] as $kk => $vv) {
                        if ($vv['welfare_value'] && $vv['welfare_value'] <= $t['price']) {
                            $t['info'][$kk]['wv'] = $vv['welfare_value'];
                        } else {
                            $t['info'][$kk]['wv'] = 0;
                        }
                        $t['info'][$kk]['infourl'] = "/web/wappay/ordersubmit?id=" . $t['id'] . "&tid=" . $vv['id'] . "&g_buy=0";
                    }
                }
                $temp[$k] = $t;
            }


            return $this->jsonResponse(['data' => $temp]);
        }

        $view = new viewModel(array(
            'data' => $data,
            'welfare' => $welfares,
            'coupon' => $coupon,
            'n' => $count,
            'cid' => $cid,
            'username' => $username
        ));

        $view->setTerminal(true);

        return $view;
    }

    public function resultAction()
    {
        $getOptions = $this->getOptions();

        if ($getOptions->old) {
            $url = $this->_getConfig()['url'] . '/web/ground/new?dtid=' . $this->getDtid();
            header("Location: $url");
            exit;
        }
        $old = $this->isOld();

        if (!$old) {
            $url = $this->_getConfig()['url'] . '/web/ground/new?dtid=' . $this->getDtid();
            header("Location: $url");
            exit;
        }

        if (!$this->getUidByPhone()) {
            $url = $this->_getConfig()['url'] . '/web/ground?dtid=' . $this->getDtid();
            header('Location: ' . $url);
            exit;
        };

        $view = new viewModel(array());
        $view->setTerminal(true);

        return $view;
    }

    //=====================================红包分享===================================================


    //活动红包分享的城市（保持和商品一直）
    private function getGoodCityByOrder($sn){
        $sql = 'SELECT play_organizer_game.city FROM wft.play_order_info left join play_organizer_game
            on play_order_info.coupon_id = play_organizer_game.id where order_sn = ?';
        $game = (array)RedCache::fromCacheData('HB:sc:' . $sn, function () use ($sn,$sql) {
            $db = $this->_getAdapter();
            $data = $db->query($sql,array($sn))->current();
            return $data;
        }, 24 * 3600 * 7, true);
        return $game['city'];
    }

    //红包分享
    public function afterbuyAction()
    {
        $uid = array_key_exists('uid', $_COOKIE) ? $_COOKIE['uid'] : 0;

        $sn = (int)$this->getQuery('code', 0);
        $city = $this->getCity();

        $weixin = new WeiXinFun($this->getwxConfig());
        if (!$this->userInit($weixin)) {
            $url = $this->_getConfig()['url'] . '/web/ground/afterbuy?code='.$sn;
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        $ower = $this->isOwer($sn);

        $options = (array)RedCache::fromCacheData('D:share_cash:' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city]);

            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options['options']);

        if ($opt) {
            $has = 0;
            foreach ($opt as $o) {
                $price = $o[0];
                $pay = explode('-', $price);
                $cv = 0;
                if ($ower->realpay >= $pay[0] and $ower->realpay <= $pay[1]) {
                    //分享者获得现金券
                    $share_cc = explode(',', $o[1]);
                    foreach ($share_cc as $sc) {
                        $cashv = (array)RedCache::fromCacheData('D:cashv:' . $sc, function () use ($sc) {
                            $data = $this->_getCashCouponTable()->get(['id' => $sc]);
                            return $data;
                        }, 24 * 3600 * 7, true);
                        $cv += $cashv['price'];
                    }
                    $has = 1;
                    break;
                }
                if(!$cv){
                    echo '您购买的金额不在奖励范围内!';
                    exit;
                }
            }
            if(!$has){
                $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2016';
                header('Location: ' . $url);
                exit;
            }
        }else{
            echo '非法请求';
            exit;
        }

        $view = new viewModel(array(
            'options' => $options,
            'cv' => $cv,
            'jsApi' => $weixin->getsignature(),
            'coupon_name' => $ower->coupon_name,
            'url' => $this->_getConfig()['url']
        ));

        $view->setTerminal(true);

        return $view;
    }

    private function getShareInfo($weixin,$sn){
        $city = $this->getGoodCityByOrder($sn);

        $siteConfig = $weixin->getsignature();
        $siteConfig['nonceStr']=$siteConfig['noncestr'];
        $siteConfig['url']=$this->_getConfig()['url'];

        $options = (array)RedCache::fromCacheData('D:share_cash:' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city, 'type'=>1]);
            return $data;
        }, 24 * 3600 * 7, true);

        $share['img']    = $this->_getConfig()['url'].$options['shareicon'];
        $share['desc']   = $options['content'];
        $share['title']  = $options['title'];
        $share['link']   = $this->_getConfig()['url'].'/web/ground/winner?sid='.$sn;

        return [$share,$siteConfig];
    }

    //获奖者
    public function winnerAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());
        $sid = (int)$this->getQuery('sid', 0);
        $uid = array_key_exists('uid',$_COOKIE)?$_COOKIE['uid']:0;

        if (!$this->userInit($weixin) or !$uid) {
            $url = $this->_getConfig()['url'] . '/web/ground/winner?sid='.$sid;
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        $page = (int)$this->getPost('page', 1);
        $limit = (int)$this->getPost('page_num', 10);
        $page = ($page > 1) ? $page : 1;

        $city = $this->getGoodCityByOrder($sid);

        $db = $this->_getAdapter();
        $sql = 'select * from play_cash_share_link where sn = ? limit 1';
        $cc = $db->query($sql, array($sid))->current();

        if(!$this->is_share($sid)){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t='.__LINE__.'&msg=此链接已经失效';
            header('Location: ' . $url);
            exit;
        }

        if(!$cc){//用户留在微信没有返回ａｐｐ，补分享成功
            $invite = new Invite();
            $status = $invite->shareCash($uid,(int)$sid,$city);
            if(!$status){//如果奖励失败,到过期吧
                $url = $this->_getConfig()['url'] . '/web/ground/empty?err='.__LINE__.'&msg=该链接已经失效!';
                header("Location: $url");
                exit;
            }
            $url = $this->_getConfig()['url'] . '/web/ground/winner?sid='.$sid;
            header("Location: $url");
            exit;
        }

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if($cc->uid == $uid){//如果是发起者
            header('Location: '.$this->_getConfig()['url'].'/web/ground/accepted?sid='.$sid);
            exit;
        }

        $options = (array)RedCache::fromCacheData('D:share_cash:' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city]);
            return $data;
        }, 24 * 3600 * 7, true);

        $messages = $options['messages'];
        $messages = json_decode($messages);

        if($user_data and $user_data->phone){//如果是老用户
            $s = $this->oldgift($uid,$sid,0,$city);
            if(!$s){
                $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2&msg=您来晚了，现金券没有了';
                header('Location: ' . $url);
                exit;
            }
            header('Location: '.$this->_getConfig()['url'].'/web/ground/accepted?sid='.$sid);
            exit;
        }

        //14=>'分享现金红包',15=>'领取现金红包'
        $ccu = $this->_getCashCouponUserTable()->fetchLimit((($page - 1) * $limit), $limit, [],
            [
                'get_object_id' => $sid,
                'get_type' => [14,15]
            ], ['get_type'=>'desc','price' => 'DESC'])->toArray();

        $count = $this->_getCashCouponUserTable()->fetchAll(
            [
                'get_object_id' => $sid,
                'get_type' => [14,15]
            ])->count();

        foreach($ccu as $c){
            $users[] = $c['uid'];
            $imgs[] = $c['uid'];
        }

        $u = $this->_getPlayUserTable()->fetchLimit(0,30,['username','uid','img'],['uid'=>$users])->toArray();
        foreach($u as $uu){
            $users[$uu['uid']] = $uu['username'];
            $imgs[$uu['uid']] = $uu['img'];
        }

        $wap = (int)$this->getPost('wap', 0);
        if ($wap) {
            $ccu_arr = [];
            foreach($ccu as $k => $c){
                $ccu_arr[$k] = $c;
                $ccu_arr[$k]['imgs'] = $imgs[$c['uid']]?:'http://userpic.deyi.com/ucenter/data/avatar/000/57/28/99_avatar_middle.jpg';
                $ccu_arr[$k]['messages'] = $messages[$c['uid']%4];
                $ccu_arr[$k]['username'] = $users[$c['uid']];
            }
            return $this->jsonResponse(['data' => $ccu_arr]);
        }

        $share = $this->getShareInfo($weixin,$sid);

        $view = new viewModel(array(
            'data' => $ccu,
            'n' => $count,
            'sid' => $sid,
            'users' => $users,
            'imgs' => $imgs,
            'jsconfig' => $share[1],
            'share' => $share[0],
            'messages' => $messages,
        ));

        $view->setTerminal(true);

        return $view;

    }

    //领取后页面
    public function acceptedAction()
    {
        $weixin = new WeiXinFun($this->getwxConfig());
        $sid = (int)$this->getQuery('sid', 0);
        if(!$sid){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2&msg=参数错误';
            header('Location: ' . $url);
            exit;
        }
        $uid = array_key_exists('uid',$_COOKIE)?$_COOKIE['uid']:0;
        //TODO
        if (!$this->userInit($weixin) or !$uid) {
            $url = $this->_getConfig()['url'] . '/web/ground/accepted?sid='.$sid;
            $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
            header("Location: $toUrl");
            exit;
        }

        if(!$this->is_share($sid)){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t='.__LINE__.'&msg=该商品没有参与红包分享';
            header('Location: ' . $url);
            exit;
        }

        $city = $this->getGoodCityByOrder($sid);

        $user_data = $this->_getPlayUserTable()->get(array('uid' => $uid));

        //13=>'分享现金红包',14=>'领取现金红包'
        $ccu = $this->_getCashCouponUserTable()->fetchLimit(0,5, [],
            [
                'get_order_id' => $sid,
                'get_type' => [14,15]
            ], ['get_type'=>'asc','price' => 'DESC'])->toArray();

        $order = $this->isOwer($sid);

        $couponprice = $timearea = '';

        if($order){//如果是发起者
            $couponname = $user_data['title'];
            $couponprice = (int)$ccu[0]['price'];
            $cid = (int)$ccu[0]['cid'];
            $timearea = date('Y-m-d',$ccu[0]['use_stime']).'-'.date('Y-m-d',$ccu[0]['use_etime']);
        }else{
            $cid = 0;
            foreach($ccu as $v){
                if($v['uid'] == $uid){
                    $couponname = $v['title'];
                    $couponprice = (int)$v['price'];
                    $cid = (int)$v['cid'];
                    $timearea = date('Y-m-d',$v['use_stime']).'-'.date('Y-m-d',$v['use_etime']);
                }
            }
            if(!$cid){
                $ccut = $this->_getCashCouponUserTable()->fetchLimit(0,1, [],
                    [
                        'get_order_id' => $sid,
                        'get_type' => [15],
                        'uid' => $uid,
                    ], ['get_type'=>'asc','price' => 'DESC'])->current();
                $couponname = $ccut->title;
                $couponprice = $ccut->price;
                $cid = $ccut->cid;
                $timearea = date('Y-m-d',$ccut->use_stime).'-'.date('Y-m-d',$ccut->use_etime);
            }
        }

        if(!$cid){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2&msg=现金券被抢光了～～';
            header('Location: ' . $url);
            exit;
        }

        $options = (array)RedCache::fromCacheData('D:share_cash:' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city]);
            return $data;
        }, 24 * 3600 * 7, true);

        $messages = $options['messages'];
        $messages = json_decode($messages);
        $imgs = $users = [];
        foreach($ccu as $c){
            $users[] = $c['uid'];
            $imgs[] = $c['uid'];
        }

        if(!$users){
            $url = $this->_getConfig()['url'] . '/web/wappay/nindex';
            header('Location: ' . $url);
            exit;
        }

        $u = $this->_getPlayUserTable()->fetchLimit(0,30,['username','uid','img'],['uid'=>$users])->toArray();

        foreach($u as $uu){
            $users[$uu['uid']] = $uu['username'];
            $imgs[$uu['uid']] = $uu['img'];
        }

        //接券商品
        $canbuy = $this->canbuy($cid,$city);

        if(!count($canbuy)){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2&msg=暂时没有可以使用的商品';
            header('Location: ' . $url);
            exit;
        }

        $goods = $canbuy['goods'];
        $coupon = $canbuy['coupon'];
        $count = $canbuy['count'];
        if (!$goods) {
            return $this->jsonResponse([]);
        }

        foreach ($goods as $g) {
            $res = [];
            $res['id'] = $g['id'];
            $res['cover'] = $this->getImgUrl($g['cover']);
            $res['title'] = $g['title'];
            $res['price'] = $g['low_price'];
            $res['end_time'] = $g['end_time'];
            $res['has'] = ($g['ticket_num'] - $g['buy_num']) ?: 0;
            $res['refund'] = ($g['refund_time'] > time()) ? '支持退款' : '不支持退款';
            $res['editor_talk'] = $g['editor_talk'];
            $res['tag'] = GoodCache::getGameTags($g['id'], ($g['post_award'] == 2));
            $res['info'] = CouponCache::getGameInfos($g['id']);
            $res['gurl'] = '/web/organizer/shops?id=' . $g['id'];

            $data[] = $res;
        }

        $wap = (int)$this->getPost('wap', 0);
        if ($wap) {
            return $this->jsonResponse(['data' => $data]);
        }

        $share = $this->getShareInfo($weixin,$sid);

        $view = new viewModel(array(
            'data' => $ccu,
            'n' => $count,
            'sid' => $sid,
            'users' => $users,
            'imgs' => $imgs,
            'username' => $user_data->username,
            'avast' => $user_data->img,
            'messages' => $messages,
            'couponname' => $couponname,
            'couponprice' => $couponprice,
            'timearea' => $timearea,
            'gdata' => $data,
            'jsconfig' => $share[1],
            'share' => $share[0],
            'phone' => array_key_exists('phone',$_COOKIE)?$_COOKIE['phone']:0
        ));

        $view->setTerminal(true);

        return $view;
    }

    //新用户领奖
    private function newgift($uid,$sid,$new,$city){
        $cid = $this->getcid($sid,$new);
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');

        $sql = 'select * from wft.play_cashcoupon_user_link where get_order_id = ? and get_type = ? and uid = ?';
        $exit = $db->query($sql, array($sid,15,$uid));
        if(count($exit)){
            return true;
        }

        $sql = "update `play_cash_share_item` set resule = resule-1 where resule > 0 and sid = ? and cid = ? limit 1";

        $status = $db->query($sql, array($sid,$cid));

        $s = 0;
        if($status){
            $cc = new Coupon();
            $s = $cc->addCashcoupon($uid,$cid,$sid,15,0,'获得分享红包现金券',$city,$sid);
        }else{
            return false;
        }

        return $s;
    }

    //老用户领奖
    private function oldgift($uid,$sid,$new,$city){
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = 'select * from wft.play_cashcoupon_user_link where get_order_id = ? and get_type = ? and uid = ?';
        $exit = $db->query($sql, array($sid,15,$uid));

        if(count($exit)){
            return true;
        }

        $cid = $this->getcid($sid,$new);
        $sql = "update `play_cash_share_item` set resule = resule-1 where resule > 0 and sid = ? and cid = ? limit 1";
        $status = $db->query($sql, array($sid,$cid))->count();

        if($status){
            $cc = new Coupon();
            $s = $cc->addCashcoupon($uid,$cid,$sid,15,0,'获得分享红包现金券',$city,$sid);
        }else{
            return false;
        }
        return $s;
    }

    /**
     * 是否参与购买分享
     * @param $id
     * @return bool
     */
    private function is_share($sid){
        $oit = $this->_getPlayOrderInfoTable()->fetchLimit(0,1,[],['order_sn'=>$sid,'pay_status'=>[2,5,6,7]])->current();
        if($oit and $oit->coupon_id){
            $id = $oit->coupon_id;
        }else{
            return false;
        }
        $city = $this->getAdminCity();
        $cst = $this->_getPlayCashShareTable()->get(['city'=>$city,'type'=>1]);
        if($cst->isall){
            return true;
        }else{
            $ogt = $this->_getPlayOrganizerGameTable()->get(['id'=>$id]);
            if($ogt and $ogt->cash_share){
                return true;
            }
        }
        return false;
    }

    public function emptyAction(){
        $msg = $this->getQuery('msg','抢完了，去玩翻天逛逛吧～');
        $view = new viewModel(array(
            'msg' => $msg,
        ));
        $view->setTerminal(true);

        return $view;
    }

    //抢
    public function grabAction(){
        return $this->jsonResponse(['status'=>0,'msg'=>'抱歉，红包已经被抢完了']);

        $phone = (int)$this->getPost('tel', 0);
        $sid = $this->getPost('sid', 0);
        $uid = array_key_exists('uid',$_COOKIE)?$_COOKIE['uid']:0;
        if($phone < 10086){
            return $this->jsonResponse(['status'=>0,'msg'=>'请填写正确手机号!']);
        }

        $city = $this->getPost('city', 'WH');

        if(!$this->is_share($sid)){
            return $this->jsonResponse(['status'=>1,'msg'=>'该商品没有参与红包分享!']);
        }

        if($uid){
            $ccu = $this->_getCashCouponUserTable()->fetchLimit(0,1, [],
                [
                    'uid' => $uid,
                    'get_object_id' => $sid,
                    'get_type' => [14,15]
                ])->count();

            if($ccu){
                return $this->jsonResponse(['status'=>1,'msg'=>'您已领取过哦!']);
            }
        }

        $user_data = $this->_getPlayUserTable()->get(array('phone' => $phone));
        if ($user_data) {
            $new = 0;
            //将此微信号绑定到对应手机号用户
            $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid)); //微信授权相关信息
            $this->initWeiXinData($user_data->uid, $weixin_data->open_id, $weixin_data->unionid, $user_data->token);
            $this->setCookie($user_data->uid, $user_data->token, $weixin_data->open_id, $phone); //新数据  //需要保留token与客户端相同
            $s = $this->oldgift($uid,$sid,$new,$city);
        } else {
            $new = 1;
            //注册
            $code = mt_rand(10000,99999);
            $token = md5(md5($code) . time());
            $res = $this->_getPlayUserTable()->update(array( //todo
                'token' => $token,
                'mark_info' => 0,
                'is_online' => 1,
                'status' => 1,
                'password' => '',
            ), array('uid' => $uid));
            $s = 0;
            if($res){
                $weixin_data = $this->_getPlayUserWeiXinTable()->get(array('uid' => $uid)); //微信授权相关信息
                $this->initWeiXinData($uid, $weixin_data->open_id, $weixin_data->unionid, $token);
                $this->setCookie($uid, $token, $weixin_data->open_id, $phone); //新数据  // 需要保留token与客户端相同
                $s = $this->newgift($uid,$sid,$new,$city);
            }
        }

        if($s){
            return $this->jsonResponse(['status'=>1,'msg'=>'成功了']);
            exit;
        }else{
            return $this->jsonResponse(['status'=>0,'msg'=>'抱歉，红包已经被抢完了']);
            exit;
        }

    }

    private function getcid($sid,$new){

        $r = mt_rand(1,100);

        if($r < 80 and $new){
            $order = 'cc.price desc';
        }elseif($r < 80 ){
            $order = 'cc.price asc';
        }else{
            $order = 'csi.resule desc';
        }
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $sql = "SELECT * FROM wft.play_cash_share_item as csi
left join play_cash_coupon as cc on csi.cid = cc.id where csi.sid = ? and csi.resule > 0 order by ? limit 1";

        $cc = $db->query($sql, array($sid,$order))->current();

        if($cc){
            return $cc->cid;
        }else{
            $sql = "SELECT * FROM wft.play_cash_share_item as csi
left join play_cash_coupon as cc on csi.cid = cc.id where csi.sid = ? and csi.resule > 0 order by csi.resule desc limit 1";
            $cc = $db->query($sql, array($sid))->current();
            if(!$cc){
                return 0;
            }
            return $cc->cid;
        }
    }

    //执行分享
    public function doshare()
    {
        $uid = array_key_exists('uid', $_COOKIE) ? $_COOKIE['uid'] : 0;
        $sn = (int)$this->getQuery('sn', 0);

        if(1){
            echo '没有参与红包分享';
            exit;
        }

        $cc = new Coupon();
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $ower = $this->isOwer($sn);

        if ($ower and ((int)$ower->pay_status === 2 || (int)$ower->pay_status === 5) ) {
            $order = $ower;
        } else {
            return false;
        }

        $city = 'WH';

        $options = (array)RedCache::fromCacheData('D:share_cash:' . $city, function () use ($city) {
            $data = $this->_getPlayCashShareTable()->get(['city' => $city]);
            return $data;
        }, 24 * 3600 * 7, true);

        $opt = json_decode($options['options']);
        if(!$opt){
            echo '非法１';
            exit;
        }

        $sql = "select * from play_cash_share_link where sn = ? limit 1";
        $csl = $db->query($sql, array($sn))->current();

        if($csl){
            return false;
        }

        $sql = "INSERT INTO `play_cash_share_link` (`sn`,`createtime`,`uid`,`city`,`endtime`) VALUES (?,?,?,?,0)";

        $status = $db->query($sql, array($sn, time(), $uid, $city));

        if($status){
            $sid = $db->query('select last_insert_id()',array())->current();
            if($sid){
                $sid = (int)$sid['last_insert_id()'];
            }

        }else{
            exit;
        }
        $total_money = bcadd($ower->account_money, $ower->realpay, 2);  //账户加银行卡总支付金额

        $has = 0;
        foreach ($opt as $o) {
            $price = $o[0];
            $pay = explode('-', $price);

            if ($ower and $total_money >= $pay[0] and $total_money <= $pay[1]) {
                //分享者获得现金券
                $share_cc = explode(',', $o[1]);
                foreach ($share_cc as $sc) {
                    $cc->addCashcoupon($uid, $sc, $sid, 14, 0, '购买' . $ower->coupon_name . '分享现金红包', $city, $sn);
                }

                //生成待领取的现金券
                $sql = 'insert into play_cash_share_item (`cid`,`resule`,`createtime`,`enttime`,`sid`) VALUES ';
                $prize_cc = explode(',', $o[2]);
                $value_str = '';
                foreach ($prize_cc as $pc) {
                    $pc_arr = explode('-', $pc);
                    $value_str .= '(' . $pc_arr[0] . ',' . $pc_arr[1] . ',' . time() . ',0,'.$sid.'),';
                }

                $sql .= rtrim($value_str, ',');

                $status = $db->query($sql, array());
                $has = 1;
                break;
            }
        }

        if(!$has){
            $url = $this->_getConfig()['url'] . '/web/ground/empty?t=2&msg=废弃action';
            header('Location: ' . $url);
            exit;
        }

        exit;
    }

    //判断是否发起者
    private function isOwer($sn)
    {
        $uid = array_key_exists('uid', $_COOKIE) ? $_COOKIE['uid'] : 0;
        $db = $this->_getAdapter();
        //判断是否购买过当前商品
        $sql = 'select * from play_order_info where order_sn = ? and user_id = ? limit 1';

        $order = $db->query($sql, array($sn,$uid))->current();

        return $order;
    }


    //=============================================通用调试==============================================

    public function clearAction()
    {

        $test = explode('test', $this->_getConfig()['url']);
        if (count($test) < 2) {
            exit;
        }

        $phone = (array_key_exists('phone', $_COOKIE) && $_COOKIE['phone']) ? $_COOKIE['phone'] : false;
        $openid = (array_key_exists('open_id', $_COOKIE) && $_COOKIE['open_id']) ? $_COOKIE['open_id'] : false;
        $untime = time() - 3600;  //失效时间
        setcookie('uid', 0, $untime, '/');
        setcookie('token', 0, $untime, '/');
        setcookie('open_id', 0, $untime, '/');
        setcookie('phone', 0, $untime, '/');
        setcookie('oldhas', 0, $untime, '/');
        setcookie('old', 0, $untime, '/');
        setcookie('dtid', 0, $untime, '/');
        setcookie('cid', 0, $untime, '/');

        var_dump('ok', $_COOKIE);
    }

    public function yu86fabf00d5dt9ii4a5ff87Action()
    {
        exit;
        $test = explode('test', $this->_getConfig()['url']);
        if (count($test) < 2) {
            echo '404';
            exit;
        }
        $uid = (array_key_exists('uid', $_COOKIE) && $_COOKIE['uid']) ? $_COOKIE['uid'] : false;
        $this->_getPlayAccountTable()->update(['now_money' => 9999], ['uid' => $uid]);

        $url = $this->_getConfig()['url'] . '/web/ground/confirm';
        // header("Location: $url");
        var_dump('ok', $_COOKIE);
        exit;
    }

    public function cookAction()
    {
        echo '<pre>';
        var_dump('ok', $_COOKIE);
        exit;
    }

    public function unuserAction()
    {
        exit;
        $test = explode('test', $this->_getConfig()['url']);
        if (count($test) < 2) {
            exit;
        }
        $this->_getCashCouponUserTable()->delete(['uid' => $_COOKIE['uid']]);

        $phone = (array_key_exists('phone', $_COOKIE) && $_COOKIE['phone']) ? $_COOKIE['phone'] : false;
        $openid = (array_key_exists('open_id', $_COOKIE) && $_COOKIE['open_id']) ? $_COOKIE['open_id'] : false;

        $untime = time() - 3600;  //失效时间
        setcookie('uid', 0, $untime, '/');
        setcookie('token', 0, $untime, '/');
        setcookie('cid', 0, $untime, '/');
        setcookie('open_id', 0, $untime, '/');
        setcookie('dtid', 0, $untime, '/');
        setcookie('phone', 0, $untime, '/');

        $untime = time() - 3600;  //失效时间
        setcookie('uid', 0, $untime, '/web');
        setcookie('token', 0, $untime, '/web');
        setcookie('cid', 0, $untime, '/web');
        setcookie('dtid', 0, $untime, '/web');
        setcookie('open_id', 0, $untime, '/web');
        setcookie('phone', 0, $untime, '/web');

        $this->_getWeixinDituiLogTable()->delete(['phone' => $phone, 'id >?' => 0]);
        $this->_getWeixinDituiLogTable()->delete(['open_id' => $openid, 'id >?' => 0]);

        $this->_getPlayUserTable()->delete(['phone' => $phone]);
        $this->_getPlayUserTable()->delete(['username' => $_COOKIE['nkname']]);
        $this->_getPlayUserTable()->delete(['uid' => $_COOKIE['uid']]);
        $this->_getPlayUserWeiXinTable()->delete(['open_id' => $openid, 'id >?' => 0]);

        $this->_getPlayUserWeiXinTable()->delete(['open_id' => $openid]);

        $this->_getPlayOrderInfoTable()->delete(['phone' => $phone]);


        var_dump('ok', $_COOKIE);
        exit;

    }

}