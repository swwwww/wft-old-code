<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ApiUser\Controller;

use Deyi\BaseController;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Http\Response;
use Deyi\JsonResponse;
use Deyi\Validation;

class PhoneController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    //联系人列表
    public function indexAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = $this->getParams('uid');
        if (!$uid) {
            return $this->jsonResponseError('用户不存在');
        }

        $uid_info = $this->_getPlayUserTable()->get(array('uid' => $uid));

        $phone_list = $this->_getPlayUserLinkerTable()->fetchAll(array('user_id' => $uid), array('is_default' => 'desc'));

        $data_list = array();

        $phone_number = $phone_list->count();
        if ($uid_info->phone and $phone_number == 0) {
            $data_list[] = array('id' => '0', 'name' => $uid_info->username, 'phone' => $uid_info->phone);
        }

        foreach ($phone_list as $v) {
            //add by wzxiang 2016.4.12 增加邮编，省份，城市，联系地址，默认联系人字段
            $data_list[] = array('id' => $v->linker_id, 'name' => $v->linker_name, 'phone' => $v->linker_phone,
                'post_code' => $v->linker_post_code, 'province' => $v->province, 'city' => $v->city, 'region' => $v->region,
                'address' => $v->linker_addr, 'is_default' => $v->is_default);
        }
        return $this->jsonResponse($data_list);
    }

    //修改手机号
    public function editphoneAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $uid = (int)$this->getParams('uid');
        $id = (int)$this->getParams('id');

        $phone = $this->getParams('phone');
        $name = $this->getParams('name');

        if (!Validation::isMobile($phone)) {
            return $this->jsonResponseError('手机号码格式错误');
        }
        $province = $this->getParams('province');
        $city = $this->getParams('city');
        $region = $this->getParams('region');
        $address = $this->getParams('address');
        if (empty($address)) {
            return $this->jsonResponseError('详细地址不能为空');
        }

        if (!empty($id)) {
            $status = $this->_getPlayUserLinkerTable()->update(array('linker_name' => $name, 'linker_phone' => $phone,
                'province' => $province, 'city' => $city, 'region' => $region, 'linker_addr' => $address), array('linker_id' => $id));
        } else {
            $is_default = 1;
            $playUserLinker = $this->_getPlayUserLinkerTable()->get("user_id={$uid} and is_default=1");
            if (!empty($playUserLinker)) {
                $is_default = 0;
            }
            $status = $this->_getPlayUserLinkerTable()->insert(array('user_id' => $uid, 'linker_name' => $name, 'linker_phone' => $phone,
                'province' => $province, 'city' => $city, 'region' => $region, 'linker_addr' => $address, 'is_default' => $is_default));
            $id = $this->_getPlayUserLinkerTable()->getlastInsertValue();
        }

        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '操作成功', 'id' => $id));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '操作失败,内容未做改动'));
        }
    }

    //删除联系人
    public function deletephoneAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = (int)$this->getParams('id');
        $playUserLinker = $this->_getPlayUserLinkerTable();

        $userLinker = $playUserLinker->get("linker_id={$id}");
        if (empty($userLinker)) {
            return $this->jsonResponseError('默认联系人无法删除');
        }

        $status = $playUserLinker->delete(array('linker_id' => $id));
        //如果默认联系人被删除了，要再设置一个默认联系人
        if ($userLinker->is_default == 1) {
            $data = $playUserLinker->get("user_id={$userLinker->user_id}");
            if (!empty($data)) {
                $playUserLinker->update(array('is_default' => 1), "linker_id={$data->linker_id}");
            }
        }

        if ($status) {
            return $this->jsonResponse(array('status' => 1, 'message' => '删除成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '删除失败,检查数据是否存在'));
        }
    }

    /** 设置默认联系人
     *
     */
    public function setdefaultAction()
    {
        if (!$this->pass()) {
            return $this->failRequest();
        }

        $id = $this->getParams('id');
        $uid = $this->getParams('uid');

        if (empty($id) || empty($uid)) {
            return $this->jsonResponseError('联系人信息不存在');
        }

        $playUserLinker = $this->_getPlayUserLinkerTable();
        $status1 = $playUserLinker->update(array('is_default' => 0), "user_id={$uid}");
        $status2 = $playUserLinker->update(array('is_default' => 1), "linker_id={$id}");

        if ($status1 and $status2) {
            return $this->jsonResponse(array('status' => 1, 'message' => '设置默认联系人成功'));
        } else {
            return $this->jsonResponse(array('status' => 0, 'message' => '设置默认联系人失败'));
        }
    }
}
