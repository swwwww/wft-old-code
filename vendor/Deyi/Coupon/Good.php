<?php

namespace Deyi\Coupon;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class Good
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    /**
     * 统一商品的价格 及数量 及 卖出数量
     * @param $gid
     * @return bool
     */
    public function toRight($gid)
    {

        $goodData = $this->_getPlayOrganizerGameTable()->get(array('id' => $gid));
        if ($goodData->is_together != 1) { //非合作
            return false;
        }

        // 如果有套系 变 如果没有 则 更新价格
        $gameInfoData = $this->_getPlayGameInfoTable()->fetchLimit(0, 100, array(), array('gid' => $gid));

        $low_price = NULL;
        $large_price = NULL;
        $head_time = NUll; //最早的开始时间
        $foot_time = NUll; // 最晚的结束时间
        $ticket_num = 0;
        $buy_num = 0;
        $low_money = NULL;
        $shop_address = [];
        $up_time = 0; //开始售卖时间
        $down_time = 0; //停止退款时间
        //$refund_time = 0; //停止退款时间

        foreach ($gameInfoData as $infoData) {
            if($infoData->status > 0) {
                if ($up_time) {
                    if ($up_time > $infoData->up_time) {
                        $up_time = $infoData->up_time;
                    }
                } else {
                    $up_time = $infoData->up_time;
                }

                if ($down_time) {
                    if ($down_time < $infoData->down_time) {
                        $down_time = $infoData->down_time;
                    }
                } else {
                    $down_time = $infoData->down_time;
                }

               /* if ($refund_time) {
                    if ($refund_time < $infoData->refund_time) {
                        $refund_time = $infoData->refund_time;
                    }
                } else {
                    $refund_time = $infoData->refund_time;
                }*/

                if ($foot_time) {
                    if ($foot_time < $infoData->end_time) {
                        $foot_time = $infoData->end_time;
                    }
                } else {
                    $foot_time = $infoData->end_time;
                }

                if ($head_time) {
                    if ($head_time > $infoData->start_time) {
                        $head_time = $infoData->start_time;
                    }
                } else {
                    $head_time = $infoData->start_time;
                }

                if ($large_price) {
                    if ($large_price < $infoData->price) {
                        $large_price = $infoData->price;
                    }
                } else {
                    $large_price = $infoData->price;
                }

                if ($low_price) {
                    if ($low_price > $infoData->price) {
                        $low_price = $infoData->price;
                        $low_money = $infoData->money;
                    }
                } else {
                    $low_price = $infoData->price;
                    $low_money = $infoData->money;
                }
                $shop_address[] = $infoData->shop_circle;
            }
            $ticket_num = $ticket_num + $infoData->total_num;
            $buy_num = $buy_num + $infoData->buy;
        }

        //如果是团购商品 则最低价显示团购价格
        if ($goodData->g_buy && $goodData->g_price) {
            $low_price = ($goodData->g_price < $low_price) ? $goodData->g_price : $low_price;
        }

        $shop_circle = array_unique($shop_address);
        $shop_circle = array_filter($shop_circle);

        if (count($shop_circle) > 1) {
            $shop_addr = '多个商圈';
        } else {
            $shop_addr = $shop_circle[0];
        }

        $this->_getPlayOrganizerGameTable()->update(array(
            'low_price' => $low_price,
            'large_price' => $large_price,
            'head_time' => $head_time,
            'foot_time' => $foot_time,
            'up_time' => $up_time,
            'down_time' => $down_time,
            'ticket_num' => $ticket_num,
            'shop_addr' => $shop_addr,
            'low_money' => $low_money,
            'buy_num' => $buy_num,
        ), array('id' => $gid));


        return true;
    }


    /**
     * 处理商品分类
     * @param $id
     * @param $labelIds
     * @return bool
     */
    public function doLabel($id, $labelIds)
    {

        $adapter = $this->_getAdapter();
        $this->_getPlayLabelLinkerTable()->delete(array('object_id' => $id, 'link_type' => 2));

        if (count($labelIds)) {
            $i = 1;
            $sql = "INSERT INTO play_label_linker (lid, object_id, link_type) VALUES";
            foreach ($labelIds as $val) {
                if ($i == 1) {
                    $sql = $sql . "({$val} , {$id}, 2)";
                } else {
                    $sql = $sql . ", ({$val} , {$id}, 2)";
                }
                $i++;
            }
            if ($i > 1) {
                $adapter->query($sql, array())->count();
            }
        }
        return true;
    }


    /**
     * 处理商品标签属性
     * @param $id
     * @param $tagIds
     * @return bool
     */
    public function doWithTag($id, $tagIds)
    {
        $adapter = $this->_getAdapter();
        $this->_getPlayTagsLInkTable()->delete(array('link_id' => $id, 'tag_type' => 1));

        if (count($tagIds)) {
            $i = 1;
            $sql = "INSERT INTO play_tags_link (tag_id, link_id, tag_type) VALUES";
            foreach ($tagIds as $val) {
                if ($i == 1) {
                    $sql = $sql . "({$val} , {$id}, 1)";
                } else {
                    $sql = $sql . ", ({$val} , {$id}, 1)";
                }
                $i++;
            }
            if ($i > 1) {
                $adapter->query($sql, array())->count();
            }
        }
        return true;
    }
}
