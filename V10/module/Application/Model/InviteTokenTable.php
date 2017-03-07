<?php
/**
 * Created by PhpStorm.
 * User: kylin
 * Date: 2015/12/25
 * Time: 10:35
 */
namespace Application\Model;

class InviteTokenTable extends BaseTable
{
    protected $primaryKey = 'inviteid';
    private   $_cachePrefix = 'invitetoken_';

    public function getByToken($token/*, $dbSource = false*/)
    {
/*        $data = null;
        $key = $this->_cachePrefix. $token;
        $cache = RedCache::get($key);
        if (!$cache || $dbSource) {*/
            $data = $this->fetchLimit(0, 1, [], ['token' => $token])->current();
     /*       if ($data) {
                RedCache::set($key, json_encode($data), 7200);
            }
        } else {
            $data = json_decode($cache);
        }*/

        return $data;
    }

    public function getToken($ruleId, $uid,$column = ['token']/*, $dbSource = true*/)
    {
         $data = null;
        /*                $key = $this->_cachePrefix. '_'.$typeId.'_'.$uid;
                        $cache = RedCache::get($key);
                        if (!$cache || $dbSource) {*/
            $result = $this->fetchLimit(0, 1, $column, [ 'ruleid' => $ruleId, 'uid' => $uid])->current();
            if($result){
                if($column == ['token']){
                    $data = $result->token;
                }else{
                    $data = $result;
                }

            }
/*            if ($data) {
                $data = $data->token;
                RedCache::set($key, $data, 7200);
            }
        } else {
            $data = $cache;
        }*/

        return $data;
    }

    public function setToken($ruleId, $accountId, $username='')
    {
//        $token = substr(md5($ruleId.time().$accountId), -8);
        $token = strtoupper(base_convert($accountId+123456789,10,32));//重置token规则
        $this->insert([ 'ruleid' => $ruleId, 'uid' => $accountId, 'username' => $username, 'token' => $token]);
        return $token;
    }

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