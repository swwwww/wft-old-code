<?php

namespace library\Model;


use library\Service\System\Cache\RedCache;

class PlayUserCollectTable extends BaseTable
{
    //获取商家名称
    public function getAdminCollectShopName($start = 0, $pagesum = 0, $columns = array(), $where = array(), $order = array())
    {
        $data = $this->tableGateway->select(function ($select) use ($start, $pagesum, $columns, $where, $order) {
            $select->join('play_shop', 'play_shop.shop_id = play_user_collect.link_id', array('shop_name'), 'left');
            $select->where($where);
            if ($pagesum) {
                $select->limit($pagesum)->offset($start);
            }
            $select->order($order);
            $select->Group(array('play_user_collect.uid'));

        });
        return $data;
    }


    //获取收藏数据
    public function getCollect($uid, $type, $link_id)
    {
       return RedCache::fromCacheData("D:collect:{$uid}:{$type}:{$link_id}", function () use ($uid, $type, $link_id) {
            return $this->get(array('uid' => $uid, 'type' => $type, 'link_id' => $link_id));
        }, 3600 * 24 * 3, true);
    }

    //收藏
    public function collect($uid, $type, $link_id)
    {

        $data = array('uid' => $uid, 'type' => $type, 'link_id' => $link_id, 'add_time' => time());
        $status = parent::insert($data);
        if ($status) {
            RedCache::updateCache("D:collect:{$uid}:{$type}:{$link_id}", $data);
        }
    }

    //取消收藏
    public function unCollect($uid, $type, $link_id)
    {
        $status = parent::delete(array('uid' => $uid, 'type' => $type, 'link_id' => $link_id));
        if ($status) {
            //取消点赞
            RedCache::del("D:collect:{$uid}:{$type}:{$link_id}");
        }
    }


}