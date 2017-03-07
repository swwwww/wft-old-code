<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/28
 * Time: 10:35
 */
namespace Application\Model;

class InviteRuleTable extends BaseTable
{

    public function getByRuleId($city='WH'/*, $dbSource = false*/)
    {
/*        $data = null;
        $key = $this->_cachePrefix. $token;
        $cache = RedCache::get($key);
        if (!$cache || $dbSource) {*/
            $data = $this->fetchLimit(0, 1, [], ['city' => $city])->current();
     /*       if ($data) {
                RedCache::set($key, json_encode($data), 7200);
            }
        } else {
            $data = json_decode($cache);
        }*/

        return $data;
    }


    public function getByMyRuleId($ruleid/*, $dbSource = false*/)
    {
        /*        $data = null;
                $key = $this->_cachePrefix. $token;
                $cache = RedCache::get($key);
                if (!$cache || $dbSource) {*/
        $data = $this->fetchLimit(0, 1, [], ['ruleid' => $ruleid])->current();
        /*       if ($data) {
                   RedCache::set($key, json_encode($data), 7200);
               }
           } else {
               $data = json_decode($cache);
           }*/

        return $data;
    }

/*    public function getToken($ruleId, $uid)
    {
         $data = null;

            $result = $this->fetchLimit(0, 1, ['token'], [ 'ruleid' => $ruleId, 'uid' => $uid])->current();
            if($result){
                $data = $result->token;
            }


        return $data;
    }*/


    /**
     * 增加一次分享查看数
     * @param $token
     */
    public function addViews($token)
    {
        $this->update(['views' => new Expression('views + 1')], ['token' => $token]);
    }

    /**
     * 增加一次分享
     * @param $token
     */
    public function addCount($token)
    {
        $this->update(['count' => new Expression('count + 1')], ['token' => $token]);
    }
} 