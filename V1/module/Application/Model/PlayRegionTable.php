<?php
namespace Application\Model;

class PlayRegionTable extends BaseTable
{

    /*
     * 获取已rid为下标的数组
     */
    public function getList()
    {
        $data = $this->fetchAll();
        $res = array();
        foreach ($data as $v) {
            $res[$v->rid] = $v->name;
        }
        return $res;
    }

}