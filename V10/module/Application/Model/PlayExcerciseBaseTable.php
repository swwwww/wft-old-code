<?php
namespace Application\Model;

use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\NotIn;
use Zend\Db\Sql\Select;

class PlayExcerciseBaseTable extends BaseTable
{


    //活动列表
    public function getList($offset, $page_num, $sort, $city = 'WH')
    {
        $db = $this->tableGateway->getAdapter();
        if ($sort == 1) {

            $data = array();
            //开始时间距实际时间降序   缺口人数降序    显示开始到结束的时间
            $res = $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,circle,b.vir_number,b.custom_tags,b.special_labels,b.free_coupon_event_count,
	e.id as eid
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE
b.city=?
AND  b.release_status=1
AND e.sell_status>=1
AND e.sell_status!=3
AND e.start_time >(UNIX_TIMESTAMP()-1209600)
AND e.over_time>UNIX_TIMESTAMP()
AND e.customize = 0
AND e.join_number<e.perfect_number

ORDER BY
	(
e.start_time - UNIX_TIMESTAMP()
	) ASC,
	(e.perfect_number - e.join_number) ASC", array($city))->toArray();

            //每个活动取一条
            foreach ($res as $k => $v) {
                if (!isset($data[$v['bbid']])) {
                    $data[$v['bbid']] = $v;
                }
            }
            //重建索引
            $d = array_values($data);
            //分页
            return array_slice($d, $offset, $page_num);
        } elseif ($sort == 2) {
            //最新
            $data = array();
            $res = $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,b.vir_number,b.custom_tags,b.special_labels,b.free_coupon_event_count,
	e.id as eid
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE
b.city=?
AND e.customize = 0
AND  b.release_status=1
AND e.sell_status>=1
AND e.sell_status!=3
AND e.over_time>UNIX_TIMESTAMP()
AND e.join_number<e.perfect_number

ORDER BY
	e.add_dateline DESC ", array($city))->toArray();

            /*WHERE b.city=? AND  b.release_status=1 AND e.sell_status>=1
AND e.sell_status <> 3

AND e.join_number<e.perfect_number
AND e.over_time >UNIX_TIMESTAMP()
            */
            //获取开始到结束时间
            foreach ($res as $k => $v) {
                if (!isset($data[$v['bbid']])) {
                    $data[$v['bbid']] = $v;
                }
            }
            //重建索引
            $d = array_values($data);
            //分页
            return array_slice($d, $offset, $page_num);
        } else {
            //历史
            $data = array();

            $res = $db->query("SELECT
	b.join_number,b.low_price,b.all_number,b.name,b.id AS bbid,b.min_end_time,max_start_time,thumb,cover,b.vir_number,b.custom_tags,b.special_labels,
	e.id as eid
FROM
	play_excercise_base AS b
LEFT JOIN play_excercise_event AS e ON e.bid = b.id
WHERE
b.city=?
AND e.customize = 0
AND  b.release_status=1
AND e.sell_status>=1
AND (e.over_time<UNIX_TIMESTAMP() or  e.join_number>=e.perfect_number)
ORDER BY
	e.add_dateline DESC ", array($city))->toArray();

            //获取开始到结束时间
            foreach ($res as $k => $v) {
                if (!isset($data[$v['bbid']])) {
                    $data[$v['bbid']] = $v;
                }
            }
            //重建索引
            $d = array_values($data);
            //分页
            return array_slice($d, $offset, $page_num);
        }
    }


    //现金券可以使用的列表
    public function getCashUseList($offset = 0, $row = 20, $columns = array(), $where = array(), $order = array(), $like = array())
    {
        $resultSet = $this->tableGateway->select(function (Select $select) use ($columns, $where, $like, $order, $offset, $row) {
            if (!empty($columns)) {
                $select->columns($columns);
            }
            if (!empty($like) and $like[key($like)]) {
                $select->where->like(key($like), "%{$like[key($like)]}%");
            }
            $select->join('play_excercise_event','play_excercise_event.bid=play_excercise_base.id',array());
            if ($row) {
                $select->limit($row)->offset($offset);
            }
            $select->where($where)->order($order);
            $select->group('play_excercise_base.id');
        });
        return $resultSet;
    }

}
