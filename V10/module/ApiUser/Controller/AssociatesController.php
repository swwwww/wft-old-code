<?php
/**
 * Created by IntelliJ IDEA.
 * User: dede20150907
 * Date: 2016/4/12
 * Time: 14:58
 */

namespace Apiuser\Controller;

use Application\Module;
use Deyi\BaseController;
use Deyi\Idverification;
use library\Fun\Common;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;

class AssociatesController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    /** 出行人列表
     * @return \Zend\View\Model\JsonModel
     */
    public function listAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponse(array('status' => 0, 'message' => '用户不存在'));
        }
        $playUserAssociates = $this->_getPlayUserAssociatesTable();

        $data = $playUserAssociates->fetchAll(array('uid' => $uid,'status'=>1))->toArray();

        return $this->jsonResponse($data);
    }

    /** 添加出行人
     * @return \Zend\View\Model\JsonModel
     */
    public function addAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param_uid   = (int)$this->getParams('uid');
        $param_name  = $this->getParams('name');
        $param_idNum = $this->getParams('id_num');

        // 参数验证
        if (empty($param_name)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '姓名格式不对哦'));
        }

        if(empty($param_idNum) || (strlen($param_idNum) != 15 && strlen($param_idNum) != 18)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '身份证格式不对哦'));
        }

        // 对用户信息进行校验
        $table_playUser = $this->_getPlayUserTable();
        $data_user      = $table_playUser->get(array("uid" => $param_uid));
        if(empty($data_user)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '这个用户找不到了啦'));
        }

        // 验证身份证号码是否有效，并获取身份号所关联的信息
        $data_idInfo = $this->checkCardId($param_idNum);
        if ($data_idInfo['errNum'] != 0) {
            return $this->jsonResponse(array('status' => 0, 'message' => '身份证号码不对哦'));
        }

        // 获取身份证号相关信息
        $data_birth = date('Ymd',strtotime($data_idInfo['retData']['birthday']));
        if($data_idInfo['retData']['sex'] == 'M'){
            $data_sex = 1;
        }else{
            $data_sex = 2;
        }

        // 进行出行人绑定数据操作
        $table_playUserAssociates = $this->_getPlayUserAssociatesTable();
        $data_checkResult         = $this->checkRepeatForIdNumOfAssociates($param_uid, $param_idNum);

        if (!$data_checkResult) {
            return $this->jsonResponse(array('status' => 0, 'message' => '该身份账号已提交，请勿重复添加哦'));
        }
        // 进行出行人的绑定
        $data_return = $table_playUserAssociates->insert(array('uid' => $param_uid, 'name' => $param_name, 'sex' => $data_sex,'birth' => $data_birth, 'id_num' => $param_idNum));
        $data_id     = $table_playUserAssociates->getlastInsertValue();

        if ($data_return) {
            return $this->jsonResponse(array('status' => 1, 'message' => '恭喜，添加出行人成功了', 'id' => $data_id));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '抱歉，添加出行人失败了'));
        }
    }

    /** 修改出行人
     * @return \Zend\View\Model\JsonModel
     */
    public function editAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $param_associatesId = (int)$this->getParams('associates_id');
        $param_name         = $this->getParams('name');
        $param_idNum        = $this->getParams('id_num');

        // 参数验证
        if (empty($param_associatesId)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '出行人信息异常，对应的出行人id无效'));
        }

        if (empty($param_name)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '姓名格式好像不对哦'));
        }

        // 进行身份证号的校验
        if(empty($param_idNum) || (strlen($param_idNum) != 15 && strlen($param_idNum) != 18)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '身份证格式好像不对哦'));
        }

        // 获取出行人记录
        $table_playUserAssociates = $this->_getPlayUserAssociatesTable();
        $data_associates          = $table_playUserAssociates->get(array("associates_id" => $param_associatesId));
        if(empty($data_associates)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '出行人信息找不到了啦'));
        }

        // 验证身份证号码是否有效，并获取身份号所关联的信息
        $data_idInfo = $this->checkCardId($param_idNum);
        if ($data_idInfo['errNum'] != 0) {
            return $this->jsonResponse(array('status' => 0, 'message' => '身份证号码不对哦'));
        }

        // 获取身份证号相关信息
        $data_birth = date('Ymd',strtotime($data_idInfo['retData']['birthday']));
        if($data_idInfo['retData']['sex'] == 'M'){
            $data_sex = 1;
        }else{
            $data_sex = 2;
        }

        $data_checkResult = $this->checkRepeatForIdNumOfAssociates($data_associates['uid'], $param_idNum);

        if (!$data_checkResult) {
            return $this->jsonResponse(array('status' => 0, 'message' => '该身份账号已提交，请换一个再试试'));
        }

        // 进行出行人的绑定
        $data_return = $table_playUserAssociates->update(array('name' => $param_name, 'sex' => $data_sex, 'birth' => $data_birth, 'id_num' => $param_idNum), array('associates_id'=>$param_associatesId));

        if ($data_return) {
            return $this->jsonResponse(array('status' => 1, 'message' => '恭喜，修改出行人成功了'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '抱歉，修改出行人失败了'));
        }
    }

    /** 删除出行人
     * @return \Zend\View\Model\JsonModel
     */
    public function deleteAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        //判断是否老版本
        $client_info = Common::getClientinfo();
        if($client_info['client']==='ios' or $client_info['client']==='android'){
            $ver = sprintf('%-03s', str_replace('.', '', $client_info['ver']));
            if ($ver < 333) {
                return $this->jsonResponse(array('status' => 0, 'message' => '升级到最新版才能删除联系人哦'));
            }
        }


        $associates_id = $this->getParams('associates_id');
        $playUserAssociates = $this->_getPlayUserAssociatesTable();
        $userData = $playUserAssociates->get("associates_id={$associates_id}");
        if (empty($userData)) {
            return $this->jsonResponse(array('status' => 0, 'message' => '用户不存在'));
        }
        $status = $playUserAssociates->update(array('status'=>0),array('associates_id' => $associates_id));

        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '删除成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '删除失败,检查数据是否存在'));
        }
    }


    /**
     * @param $code
     * @return bool
     */
    public function checkCardId($code)
    {

        // 验证身份证号码是否有效，并获取身份号所关联的信息
       return Idverification::isCard($code);



        /*
         *   $data_idInfo = $this->checkCardId($param_idNum);
        if ($data_idInfo['errNum'] != 0) {
            return $this->jsonResponse(array('status' => 0, 'message' => '身份证号码不对哦'));
        }

        // 获取身份证号相关信息
        $data_birth = date('Ymd',strtotime($data_idInfo['retData']['birthday']));
        if($data_idInfo['retData']['sex'] == 'M'){
            $data_sex = 1;
        }else{
            $data_sex = 2;
        }

        */




        $header = 'apikey: 1c6b3fbd2dfc45aeb5c0b80c4cf4c7f0';
        $ch = curl_init();
        $url = "http://apis.baidu.com/apistore/idservice/id?id=" . $code;
        $header = array(
            $header,
        );
        // 添加apikey到header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  //10秒
        // 执行HTTP请求
        curl_setopt($ch, CURLOPT_URL, $url);
        $res = curl_exec($ch);

        if (!$res) {
            return false;
        } else {
            return json_decode($res, true);
        }

//        $header = 'apikey: 1c6b3fbd2dfc45aeb5c0b80c4cf4c7f0';
//
//        $context = stream_context_create(array(
//            'http' => array(
//                'method' => 'GET',
//                'header' => $header,
//                'timeout' => 60
//            )
//        ));
//        $data = file_get_contents("http://apis.baidu.com/apistore/idservice/id?id=" . $code, false, $context);
//        if (!$data) {
//            return false;
//        } else {
//            $data = json_decode($data, true);
//            return $data;
//        }
    }

    /**
     * 检查联系人中的身份证号是否重复
     * @param uid           用户的uid
     * @param idNum         待验证的身份证号
     * @return bool
     */
    public function checkRepeatForIdNumOfAssociates($uid, $idNum) {
        // 若参数不全则默认验证失败
        if (empty($uid) || empty($idNum)) {
            return false;
        }

        $table_playUserAssociates = $this->_getPlayUserAssociatesTable();
        // 验证该身份证并没有在同一个账号下绑定过
        $data_exsit               = $table_playUserAssociates->get(array('uid' => $uid, 'id_num' => $idNum, 'status' => 1));

        return empty($data_exsit);
    }

}
