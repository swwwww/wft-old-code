<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class UserCache
{
    use BaseController;

    private $vir_i;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    public function __construct() {
        $this->cache = new RedCache();
    }

    /**
     * 获取用户基本信息
     * @param $uid
     * @return array|mixed
     */
    public function getUserInfo($uid) {

        $userInfo =  $this->cache->get('user_info_'. $uid);
        if ($userInfo) {
            return json_decode($userInfo, true);
        } else {
            return $this->_setUserInfo($uid);
        }
    }

    /**
     * 获取虚拟用户
     * @return mixed|void
     */
    public function getVirUserCache()
    {
        $virUser =  $this->cache->get('user_vir');
        if ($virUser) {
            return json_decode($virUser, true);
        } else {
            return $this->_setVirUser();
        }
    }

    /**
     * 设置虚拟用户
     * @return array
     */
    private function _setVirUser()
    {
        $userData = $this->_getPlayUserTable()->fetchLimit(0, 200, array('uid'), array('status' => 1, 'is_vir' => 1));

        if (!$userData->count()) {
            return false;
        }

        $data = array();
        foreach ($userData as $user) {
            $data[] = (int)$user['uid'];
        }

        $this->cache->set('user_vir', json_encode($data, JSON_UNESCAPED_UNICODE), 3600 * 24);
        return $data;

    }


    /**
     * 设置用户的缓存
     * @param $uid
     * @param int $type 是否获取
     * @return bool
     */
    public function setUserCache($uid, $type = 1)
    {
        $this->_setUserInfo($uid);
        $this->_setUserBaby($uid);
        $this->_setUserBabyAge($uid);
        if ($type == 2) {
            $this->_setVirUser();
        }
        return true;
    }

    /**
     * 设置用户基本信息缓存
     * @param $uid
     * @return array|bool
     */
    private function _setUserInfo($uid) {

        $userData = $this->_getPlayUserTable()->get(array('uid' => $uid));

        if (!$userData) {
            return false;
        }

        $data = array(
            'uid' => $userData->uid, //uid
            'img' => $userData->img, //头像
            'username' => $userData->username, //用户名
            'phone' => $userData->phone, //电话
            'sex' => $userData->child_sex, //性别 2 宝妈 1宝爸
            'birth' => $userData->child_old, //年龄 时间戳
            'user_alias' => $userData->user_alias, //呢称 3岁 宝爸
            'sign' => $userData->sign, //个性签名
            'is_vir' => $userData->is_vir, //是否虚拟用户
        );

        $this->cache->set('user_info_'. $uid, json_encode($data, JSON_UNESCAPED_UNICODE), 3600 * 24);

        return $data;
    }

    /**
     * 获取用户宝宝的信息
     * @param $uid
     * @return array|mixed
     */
    public function getUserBaby($uid) {

        $babyData =  $this->cache->get('user_baby_'. $uid);
        if ($babyData) {
            return json_decode($babyData, true);
        } else {
            return $this->_setUserBaby($uid);
        }
    }

    private function _setUserBaby($uid) {

        $data = array();
        $babyData = $this->_getPlayUserBabyTable()->fetchLimit(0, 3, array('baby_birth'), array('uid' => $uid));

        if (!$babyData->count()) {
            return $data;
        }

        $data['max'] = 100;
        $data['min'] = 0;
        $data['baby'] = array();
        $min = time();
        $max = 0;

        foreach ($babyData as $baby) {
            $data['baby'][] = array(
                'name' => $baby['baby_name'],
                'sex' => $baby['baby_sex'],
                'img' => $baby['img'],
                'birth' => $baby['baby_birth'],
                'id' => $baby['id'],
            );
            $max = ($baby['baby_birth'] > $max) ? $baby['baby_birth'] : $max;
            $min = ($baby['baby_birth'] < $min) ? $baby['baby_birth'] : $min;
        }

        if ($max && $min < time()) {
            $data['max'] = $this->getAge($min);
            $data['min'] = $this->getAge($max);
        }

        $this->cache->set('user_baby_'. $uid, json_encode($data, JSON_UNESCAPED_UNICODE), 3600 * 24);

        return $data;
    }



    /**
     *  取出用户宝宝的年龄大小
     */

    public function getBabyAge($uid) {

        $babyAge =  $this->cache->get('user_baby_age_'. $uid);
        if ($babyAge) {
            return json_decode($babyAge, true);
        } else {
            return $this->_setUserBabyAge($uid);
        }
    }


    private function _setUserBabyAge($uid) {
        $data = array(
            'max' => 100,
            'min' => 0,
        );

        if ($uid) { // 取出用户宝宝的年龄
            $babyData = $this->_getPlayUserBabyTable()->fetchLimit(0, 3, array('baby_birth'), array('uid' => $uid));
            $min = time();
            $max = 0;
            foreach ($babyData as $baby) {
                $max = ($baby['baby_birth'] > $max) ? $baby['baby_birth'] : $max;
                $min = ($baby['baby_birth'] < $min) ? $baby['baby_birth'] : $min;
            }
            if ($max && $min < time()) {
                $data['max'] = $this->getAge($min);
                $data['min'] = $this->getAge($max);
            }
        }

        $this->cache->set('user_baby_age_'. $uid, json_encode($data), '300');

        return $data;
    }

    private function getAge($birth)
    {

        $a = getdate(time());
        $b = getdate($birth);

        $n = array(
            1 => 31,
            2 => 28,
            3 => 31,
            4 => 30,
            5 => 31,
            6 => 30,
            7 => 31,
            8 => 31,
            9 => 30,
            10 => 31,
            11 => 30,
            12 => 31
        );
        $y = $m = $d = 0;
        if ($a['mday'] >= $b['mday']) { //天相减为正
            if ($a['mon'] >= $b['mon']) {//月相减为正
                $y = $a['year'] - $b['year'];
                $m = $a['mon'] - $b['mon'];
            } else { //月相减为负，借年
                $y = $a['year'] - $b['year'] - 1;
                $m = $a['mon'] - $b['mon'] + 12;
            }
            $d = $a['mday'] - $b['mday'];
        } else {  //天相减为负，借月
            if ($a['mon'] == 1) { //1月，借年
                $y = $a['year'] - $b['year'] - 1;
                $m = $a['mon'] - $b['mon'] + 12;
                $d = $a['mday'] - $b['mday'] + $n[12];
            } else {
                if ($a['mon'] == 3) { //3月，判断闰年取得2月天数
                    $d = $a['mday'] - $b['mday'] + ($a['year'] % 4 == 0 ? 29 : 28);
                } else {
                    $d = $a['mday'] - $b['mday'] + $n[$a['mon'] - 1];
                }
                if ($a['mon'] >= $b['mon'] + 1) { //借月后，月相减为正
                    $y = $a['year'] - $b['year'];
                    $m = $a['mon'] - $b['mon'] - 1;
                } else { //借月后，月相减为负，借年
                    $y = $a['year'] - $b['year'] - 1;
                    $m = $a['mon'] - $b['mon'] + 12 - 1;
                }
            }
        }
        if ($y && $m) {
            return $y . '.' . (int)($m * 10 / 12);
        } elseif ($y) {
            return $y;
        } elseif ($m) {
            return '0.' . (int)($m * 10 / 12);
        } else {
            return 0;
        }

    }

    /**
     * 获取指定个数的虚拟用户
     * @param $num
     * @return array
     */
    public function getVirUser($bid,$num,$is_first = 1){

        $data = RedCache::fromCacheData('U:gtvuser:' . $bid, function () use ($num) {
            $apt = $this->_getAdapter();
            $sql = "SELECT uid,img FROM wft.play_user where is_vir = 1 and img != '' ORDER BY RAND() LIMIT ?";
            $user = $apt->query($sql,[(int)$num])->toArray();
            return $user;
        }, 86400*7, true);

        if(count($data) !== (int)$num and $is_first){
            $this->setVirUser($bid,$num,1);
            $data = RedCache::get('U:gtvuser:' . $bid);
            $data = (array)json_decode($data,true);
        }

        return $data;
    }

    private function getvuser($num){
        $apt = $this->_getAdapter();
        $sql = "SELECT uid,img FROM wft.play_user where is_vir = 1 and img != '' ORDER BY RAND() LIMIT ?";
        $user = $apt->query($sql,[(int)$num])->toArray();
        return $user;
    }

    /**
     * 后台重新设置人数（一般新增）,将缓存保留7天，
     * 如果7天了，还没有人访问这个活动，虚拟头像重新缓存
     * @param $bid
     * @param $num
     * @param $is_first,是否第一次进入这个方法，
     * 递归时为0，表示不进入相等和为空的情况
     * @return bool
     */
    public function setVirUser($bid,$num,$is_first = 1){
        $this->vir_i++;
        if($this->vir_i > 100){//防止可能的循环
            return false;
        }
        $data = RedCache::get('U:gtvuser:' . $bid);
        $data = (array)json_decode($data,true);
        if(count($data)===0 and $is_first){
            $this->setVirUser($bid,$num,0);
            return true;
        }

        if(count($data) === (int)$num and $is_first){
            return true;
        }

        if(count($data) < $num){
            $user = $this->getvuser($num - count($data));
            $new_data = array_merge($data,$user);
            $new_data = json_encode($new_data);
            RedCache::set('U:gtvuser:' . $bid,$new_data,86400*7);
            $data = RedCache::get('U:gtvuser:' . $bid);
            if(count($data) < $num){
                $this->setVirUser($bid,$num,0);
            }else{
                return true;
            }
        }

        if(count($data) > $num and $is_first){
            $new_data = array_slice($data,0,$num);
            $new_data = json_encode($new_data);
            RedCache::set('U:gtvuser:' . $bid,$new_data,86400*7);
            return true;
        }
        return false;
    }





}