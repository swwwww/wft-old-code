<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;
use Deyi\OutPut;

class PrivatePartyController extends BasisController
{
    use JsonResponse;

    //包场活动列表
    public function indexAction()
    {

        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

       // $where = $this->getSearch();

        $where='1'; 
        $order = "play_private_party.id DESC";

        $sql = "SELECT
play_private_party.id,
play_private_party.coupon_id,
play_private_party.dateline,
play_private_party.reply_dateline,
play_private_party.name,
play_private_party.phone,
play_organizer_game.title
FROM play_private_party
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_private_party.coupon_id
WHERE $where ORDER BY {$order} LIMIT {$start}, {$pageSum}";

        $data = $this->query($sql);
        $countData = $this->query("SELECT count(play_private_party.id) as count_num FROM play_private_party
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_private_party.coupon_id WHERE $where")->current();
        $count = $countData['count_num'];

        //创建分页
        $url = '/wftadlogin/privateParty/index';
        $paging = new Paginator($page, $count, $pageSum, $url);

        $vm = new ViewModel(array(
            'booking' => $data,
            'pageData' => $paging->getHtml(),
        ));

        return $vm;
    }

    //标记为已读
    public function bookAction() {
        $id = (int)$this->getQuery('id');
        $partyData = $this->_getPlayPrivatePartyTable()->get(array('id' => $id));

        if (!$partyData || $partyData->reply_dateline > 1) {
            return $this->_Goto('非法操作');
        }

        $status = $this->_getPlayPrivatePartyTable()->update(array('reply_dateline' => time()), array('id' => $id));

        return $this->_Goto($status ? '成功' : '失败');

    }

    //查询条件
    private function getSearch() {

        $good_name = $this->getQuery('good_name', '');
        $good_id = (int)$this->getQuery('good_id');
        $time_start = $this->getQuery('time_start', '');
        $time_end = $this->getQuery('time_end', '');
        $reply_start = $this->getQuery('reply_start', '');
        $reply_end = $this->getQuery('reply_end', '');
        $status_type = $this->getQuery('status_type', 0);

        $city = $this->chooseCity();//根据管理员 或者编辑的城市取出数据

        $where = 'play_private_party.id > 0 AND play_organizer_game.is_private_party = 1';

        if ($good_name) {
            $where = $where. " AND play_organizer_game.title like '%".$good_name."%'";
        }

        if ($good_id) {
            $where = $where. " AND play_organizer_game.id = {$good_id}";
        }

        if ($time_start) {
            $where = $where. " AND play_private_party.dateline > ". strtotime($time_start);
        }

        if ($time_end) {
            $where = $where. " AND play_private_party.dateline < ". (strtotime($time_end) + 86400);
        }

        if ($reply_start) {
            $where = $where. " AND play_private_party.reply_dateline > ". strtotime($reply_start);
        }

        if ($reply_end) {
            $where = $where. " AND play_private_party.reply_dateline < ". (strtotime($reply_end) + 86400);
        }

        if ($status_type) {
            if ($status_type == 1) {
                $where = $where. " AND play_private_party.reply_dateline < 1 ";
            } else {
                $where = $where. " AND play_private_party.reply_dateline > 1 ";
            }
        }

        if ($city) {
            $where = $where. " AND play_organizer_game.city = '{$city}'";
        }

        return $where;
    }

    //导出
    public function outDataAction() {

        $fileName = date('Y-m-d H:i:s', time()). '包场活动.csv';

        $where = $this->getSearch();
        $sql = "SELECT
play_private_party.id,
play_private_party.coupon_id,
play_private_party.dateline,
play_private_party.reply_dateline,
play_private_party.name,
play_private_party.phone,
play_organizer_game.title
FROM play_private_party
LEFT JOIN play_organizer_game ON play_organizer_game.id = play_private_party.coupon_id
WHERE $where ORDER BY play_private_party.id DESC";

        $data = $this->query($sql);
        $head = array(
            'id',
            '提交时间',
            '回复时间',
            '商品id',
            '商品名称',
            '用户名',
            '电话号码',
            '状态',
        );

        $content = array();

        foreach ($data as $value) {
            $content[] = array(
                $value['id'],
                date('Y-m-d H:i:s', $value['dateline']),
                $value['reply_dateline'] ? date('Y-m-d H:i:s', $value['reply_dateline']) : '',
                $value['coupon_id'],
                $value['title'],
                $value['name'],
                $value['phone'],
                $value['reply_dateline'] ? '回复' : '未回复',
            );
        }
        $out = new OutPut();
        $out->out($fileName, $head, $content);
        exit;
    }

}
