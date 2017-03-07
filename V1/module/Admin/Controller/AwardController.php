<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Deyi\ImageProcessing;
use Deyi\OutPut;

class AwardController extends BasisController
{
    use JsonResponse;
    //use BaseController;


    /**
     * 领取列表
     * @return ViewModel
     */
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $title = $_GET['title'];
        $username = $_GET['username'];
        $phone = $_GET['phone'];
        $uid = $_GET['uid'];

        $where["play_award.award_id >?"] =  40;
        if ($username) {
            $where["play_user.username like ?"] = '%' . $username . '%';
        }
        if ($title) {
            $where["play_award.title like ?"] = '%' . $title . '%';
        }
        if ($phone) {
            $where["play_user.phone"] = $phone;
        }
        if ($uid) {
            $where["play_user.uid"] = $uid;
        }
        //数据
        $data = $this->_getAwardLogTable()->getAwardList($offset, $limit, array(), $where);
        //条数
        $count = $this->_getAwardLogTable()->countAward($where);
        //创建分页
        $url = '/wftadlogin/award';
        $paginator = new Paginator($page, $count, $limit, $url);

        return [
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
        ];
    }


    /**
     * 发货
     * @return ViewModel
     */
    public function addAction()
    {
        $log_id = $this->getQuery('log_id');
        if ($log_id) {
            $data = $this->_getAwardLogTable()->get(['log_id' => $log_id]);
        }
        return new ViewModel([
            'data' => $data,
        ]);
    }


    /**
     * 数据操作
     */
    public function updateAction()
    {
        $data = array(
            "status" => 2,
            'addtime' => date("Y-m-d H:i:s")
        );
        $result = $this->_getAwardLogTable()->update($data, ['award_id' => 1]);
    }

    public function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    public function awardAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $order = ['award_id' => 'asc'];
        $cols = [];
        $where["play_award.award_id >?"] =  40;

        $data = $this->_getAwardTable()->fetchLimit($offset, $limit, $cols, $where, $order);

        //条数
        $count = $this->_getAwardTable()->fetchCount($where);

        //创建分页
        $url = '/wftadlogin/award/award';
        $paginator = new Paginator($page, $count, $limit, $url);

        return [
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
        ];
    }

    public function excelAction()
    {
        $sql = "select l.uid,u.username,u.phone,l.address from play_award_log l left join play_user u on l.uid = u.uid where l.award_id = 1 and l.status = 1";
        $data = iterator_to_array($this->query($sql));

        include_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/Deyi/PHPExcel/PHPExcel.php');
        $excel = new \PHPExcel();
        //Excel表格式(根据时间需要设置表头)
        $letter = array('A', 'B', 'C', 'D');
        $tableheader = array('uid', '用户名', '联系电话', '收货地址');
        //表头写入
        for ($i = 0; $i < count($tableheader); $i++) {
            $excel->getActiveSheet()->setCellValue("$letter[$i]1", "$tableheader[$i]");
        }

        //填充表格信息（项目中一般是从数据库中取出）
        for ($i = 2; $i <= count($data) + 1; $i++) {
            $j = 0;
            foreach ($data[$i - 2] as $key => $value) {
                $excel->getActiveSheet()->setCellValue("$letter[$j]$i", $value);
                $j++;
            }
        }
        //宽度
        $excel->setActiveSheetIndex(0);
        $objActSheet = $excel->getActiveSheet();
        $objActSheet->getColumnDimension('C')->setWidth(20);
        $objActSheet->getColumnDimension('D')->setWidth(50);
        //创建Excel输入对象
        $write = new \PHPExcel_Writer_Excel5($excel);
        $outputFileName = "邮寄信息.xls";
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header("Content-Type: text/html; charset=utf-8");
        header("Content-Disposition: attachment;filename=$outputFileName");
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
        exit;
    }


    public function editAction()
    {
        $award_id = $this->getQuery('award_id');
        if ($award_id) {
            $data = $this->_getAwardTable()->get(['award_id' => $award_id]);
        }
        return ([
            'data' => $data,
        ]);
    }

    public function awardeditAction()
    {
        $award_id = intval($this->getQuery('award_id'));

        $title = $this->getPost('title');
        $rank = $this->getPost('rank');
        $time = $this->getPost('time');
        $note = $this->getPost('note');
        $num = $this->getPost('num');
        $data = array(
            "title" => $title,
            "rank" => $rank,
            "time" => $time,
            "note" => $note,
            'num' => $num
        );

        $this->_getAwardTable()->update($data, ['award_id' => $award_id]);
        return $this->_Goto('操作成功', '/wftadlogin/award/award');
    }

    public function conexcelAction()
    {

        $title = $_GET['title'];
        $username = $_GET['username'];
        $phone = $_GET['phone'];
        $uid = $_GET['uid'];
        $where["play_award.award_id >?"] =  40;
        if ($username) {
            $where["play_user.username like ?"] = '%' . $username . '%';
        }
        if ($title) {
            $where["play_award.title like ?"] = '%' . $title . '%';
        }
        
        if ($phone) {
            $where["play_user.phone"] = $phone;
        }
        if ($uid) {
            $where["play_user.uid"] = $uid;
        }
        //数据
        $data = $this->_getAwardLogTable()->getAll($where);
        $output = OutPut::out("获奖信息.csv", "", $data);
        exit;
    }

    public function statusAction()
    {
        $log_id = $this->getQuery("logid");
        $status = $this->getQuery("status");
        $result = $this->_getAwardLogTable()->update(['status' => $status], ['log_id' => $log_id]);
        if ($result) {
            return $this->_Goto('操作成功', '/wftadlogin/award/index');
        }else{
            return $this->_Goto('操作失败', '/wftadlogin/award/index');
        }
    }

}