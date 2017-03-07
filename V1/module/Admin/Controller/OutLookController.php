<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Validation;
use Deyi\ImageProcessing;
use Zend\View\Model\ViewModel;

class OutLookController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $start = ($page - 1) * $pageSum;

        $data =  $this->_getPlayWebActivityTable()->fetchLimit($start, $pageSum, array(), array('city' => $_COOKIE['city']));
        //获得总数量
        $count = $this->_getPlayWebActivityTable()->fetchALl()->count(array('city' => $_COOKIE['city']));
        //创建分页
        $url = '/wftadlogin/outlook';
        $pagination = new Paginator($page, $count, $pageSum, $url);

        return array(
            'data' => $data,
            'pageData' => $pagination->getHtml(),
            'typeData' => array('1' => '右上角', '2' => '焦点图'),
        );
    }

    public function newAction() {
        $id = $this->getQuery('id');
        $data = NULL;
        if ($id) {
             $data = $this->_getPlayWebActivityTable()->get(array('id' => $id));
        }

        return array(
            'data' => $data,
        );
    }

    public function saveAction() {
        $title = $this->getPost('title');
        $cover = $this->getPost('cover');
        $type = $this->getPost('type');
        $chain = $_POST['chain'];
        $id = $this->getPost('id');
        if (!$title || !$cover || !$type || !$chain) {
            return $this->_Goto('请检查下是否有未填写的');
        }

        $data = array(
            'title' => $title,
            'img' => $cover,
            'type' => $type,
            'chain' => $chain,
            'city' => $_COOKIE['city'],
        );

        if ($id) {
            $status = $this->_getPlayWebActivityTable()->update($data, array('id' => $id));
        } else {
            $status = $this->_getPlayWebActivityTable()->insert($data);
        }
        return $this->_Goto($status ? '成功' : '失败', '/wftadlogin/outlook');
    }

    public function deleteAction() {
        $id = $this->getQuery('id');
        $this->_getPlayWebActivityTable()->delete(array('id' => $id));
        return $this->_Goto('成功');
    }

    public function updateAction() {
        $id = $this->getQuery('id');
        $status = $this->getQuery('status');
        $this->_getPlayWebActivityTable()->update(array('status' => $status),array('id' => $id));
        return $this->_Goto('成功');
    }

    /*
     * 执行脚本
     */
    // todo　每个店铺是否有票
    /*public function shopAction() {

        $shop_list = $this->_getPlayShopTable()->fetchAll();

        $timer = time();
        foreach($shop_list as $shop) {



            $good_where = "play_game_info.shop_id = {$shop->shop_id} AND  play_organizer_game.end_time >= {$timer} AND play_organizer_game.start_time <= {$timer} AND play_organizer_game.status > 0";
            $good_sql = "SELECT
play_shop.label_id,
play_organizer_game.id AS gid,
play_organizer_game.thumb,
play_organizer_game.title,
play_organizer_game.low_price,
play_organizer_game.ticket_num,
play_organizer_game.buy_num,
play_organizer_game.foot_time,
play_organizer_game.is_together
FROM
play_shop
LEFT JOIN play_game_info ON play_shop.shop_id = play_game_info.shop_id
LEFT JOIN play_organizer_game ON play_game_info.gid = play_organizer_game.id
WHERE
$good_where
GROUP BY
play_organizer_game.id";

            $good_data = $this->query($good_sql);

            if ($good_data->count() != $shop->good_num) {
                $status = $this->_getPlayShopTable()->update(array('good_num' => $good_data->count()), array('shop_id' => $shop->shop_id));
                echo $status.'<br/>';
            }
        }
        exit;

    }

    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }*/

}
