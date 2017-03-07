<?php

namespace Admin\Controller;

use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\Validation;
use Zend\View\Model\ViewModel;

class SellerController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $id = $this->getQuery('id');
        $city = $this->getQuery('city', 'WH');
        $pagesum = 10;
        $where = array(
            'market_city' => $city,
        );
        if ($like) {
            $where['market_name like ?'] = '%' . $like . '%';
        }
        if ($id) {
            $where['market_id'] = $id;
        }
        $where['market_status >= ?'] = 0;
        $start = ($page - 1) * $pagesum;
        $data = $this->_getPlayMarketTable()->getMarketList($start, $pagesum, array(), $where, array('market_id' => 'desc'));
        //获得总数量
        $count = $this->_getPlayMarketTable()->getMarketList(0, 0, array(), $where, array())->count();
        //创建分页
        $url = '/wftadlogin/seller';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        return array(
            'data' => $data,
            'pageData' => $paginator->getHtml(),
            'cityData' => $this->_getConfig()['city'],
            'city' => $city,
        );
    }

    public function newAction() {
        $mid = (int)$this->getQuery('id');
        $city = $this->getQuery('city', 'WH');
        $data = array();
        $shopData = array();
        if ($mid) {
            $data = $this->_getPlayMarketTable()->get(array('market_id' => $mid));
            $shopData = $this->_getPlayShopTable()->fetchAll(array('shop_mid' => $mid, 'shop_status >= ?' => 0));
        }


        $rData = $this->_getPlayRegionTable()->fetchAll(array(), array('rid' => 'asc'))->toArray();

        $body = array();
        $sData = array();
        foreach ($rData as $v) {
            $sData[$v['rid']] = $v['name'];
            $id = substr((string)$v['rid'], 0, -2);
            if (substr((string)$v['rid'], -2, 2) == '00') {
                $head[$id] = $v;
            }
            $body[$id][] = $v;
        }
        foreach ($body as $k => $v) {
            unset($body[$k][0]);
        }
        $vm = new viewModel(
            array(
                'data' => $data,
                'shopType' => $this->_getConfig()['shop_type'],
                'shopData' => $shopData,
                'select_circle' => array($head, $body),
                'sData' => $sData,
                'city' => $city,
            )
        );
        return $vm;
    }

    public function changeAction() {

        //todo  根据订单情况 删除登陆账号
        $type = $this->getQuery('type');
        $sid = (int)$this->getQuery('sid');
        if ($type == 'del') {
            $mId = (int)$this->getQuery('mid');
            $this->_getPlayMarketTable()->update(array('market_status' => -1), array('market_id' => $mId));
            $this->_getPlayShopTable()->update(array('shop_status' => -1), array('shop_mid' => $mId));
            return $this->jsonResponsePage(array('status' => 1, 'message' => '关闭商家成功',));
            $status = $this->_getPlayShopTable()->update(array('shop_status' => -1), array('shop_id' => $sid));
        }
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '失败'));
        }
    }

    public function saveAction() {
        $market_id = (int)$this->getPost('market_id');
        $market_name = $this->getPost('market_name');
        $market_type = $this->getPost('market_type');
        $market_city = $this->getPost('city');

        if (!$market_name) {
            return $this->_Goto('商家名称 未填写');
        }

        $data = array(
            'market_name' => $market_name,
            'market_type' => $market_type,
        );
        if ($market_id) {
            $status = $this->_getPlayMarketTable()->update($data, array('market_id' => $market_id));
        } else {
            $data['market_city'] = $market_city;
            $status = $this->_getPlayMarketTable()->insert($data);
        }
        if ($status) {
            return $this->_Goto('保存成功', '/wftadlogin/seller?city='. $market_city);
        } else {
            return $this->_Goto('保存失败');
        }
    }

    public function shopListAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $city = $this->getQuery('city', 'WH');
        $pagesum = 10;
        $where = array(
            'shop_city' => $city,
            'shop_status >= ?' => 0,
        );
        if ($like) {
            $where['shop_name like ?'] = '%' . $like . '%';
        }
        $start = ($page - 1) * $pagesum;
        $data = $this->_getPlayShopTable()->getShopList($start, $pagesum, array(), $where, array(), array('market_id' => 'asc'));
        //获得总数量
        $count = $this->_getPlayShopTable()->getShopList(0, 0, array(), $where, array())->count();
        //创建分页
        $url = '/wftadlogin/seller/shoplist';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml()
        );
    }

    //商家列表
    public function marketListAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $like = $this->getQuery('k', '');
        $id = $this->getQuery('id');
        $city = $this->getQuery('city', 'WH');
        $pagesum = 10;
        $where = array(
            'market_city' => $city,
        );
        if ($like) {
            $where['market_name like ?'] = '%' . $like . '%';
        }
        if ($id) {
            $where['market_id'] = $id;
        }
        $where['market_status >= ?'] = 0;
        $start = ($page - 1) * $pagesum;
        $data = $this->_getPlayMarketTable()->getMarketList($start, $pagesum, array(), $where, array());
        //获得总数量
        $count = $this->_getPlayMarketTable()->getMarketList(0, 0, array(), $where, array())->count();
        //创建分页
        $url = '/wftadlogin/seller/marketlist';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml()
        );
    }

    public function shopAction()
    {
        $shopId = (int)$this->getQuery('id');
        $marketId = (int)$this->getQuery('marketId');

        if (!$marketId) {
            return $this->_Goto('商家那去了');
        }
        $data = array();
        if ($shopId) {
            $data = $this->_getPlayShopTable()->get(array('shop_id' => $shopId));
        }
        $marketData = $this->_getPlayMarketTable()->get(array('market_id' => $marketId));


        $rdata = $this->_getPlayRegionTable()->fetchAll(array(), array('rid' => 'asc'))->toArray();
        $body = array();
        $sData = array();
        foreach ($rdata as $v) {
            $sData[$v['rid']] = $v['name'];
            $id = substr((string)$v['rid'], 0, -2);
            if (substr((string)$v['rid'], -2, 2) == '00') {
                $head[$id] = $v;
            }
            $body[$id][] = $v;
        }
        foreach ($body as $k => $v) {
            unset($body[$k][0]);
        }

        $vm = new viewModel(
            array(
                'data' => $data,
                'marketData' => $marketData,
                'select_circle' => array($head, $body),
                'sData' => $sData,
            )
        );
        return $vm;
    }

    public function marketAction()
    {
        $mid = (int)$this->getQuery('id');
        $data = array();
        $shopData = array();
        if ($mid) {
            $data = $this->_getPlayMarketTable()->get(array('market_id' => $mid));
            $shopData = $this->_getPlayShopTable()->fetchAll(array('shop_mid' => $mid, 'shop_status >= ?' => 0));
        }


        $rData = $this->_getPlayRegionTable()->fetchAll(array(), array('rid' => 'asc'))->toArray();

        $body = array();
        $sData = array();
        foreach ($rData as $v) {
            $sData[$v['rid']] = $v['name'];
            $id = substr((string)$v['rid'], 0, -2);
            if (substr((string)$v['rid'], -2, 2) == '00') {
                $head[$id] = $v;
            }
            $body[$id][] = $v;
        }
        foreach ($body as $k => $v) {
            unset($body[$k][0]);
        }
        $vm = new viewModel(
            array(
                'data' => $data,
                'shopType' => $this->_getConfig()['shop_type'],
                'shopData' => $shopData,
                'select_circle' => array($head, $body),
                'sData' => $sData,
            )
        );
        return $vm;
    }

    public function saveShopAction()
    {
        $market_id = (int)$this->getPost('market_id');
        $shop_id = (int)$this->getPost('shop_id');

        $shop_open = strtotime($this->getPost('shop_open'));
        $shop_close = strtotime($this->getPost('shop_close'));
        $shop_phone = $this->getPost('shop_phone');
        $shop_address = $this->getPost('shop_address');
        $addr_x = $this->getPost('addr_x');
        $addr_y = $this->getPost('addr_y');
        $busniess_circle = $this->getPost('business');

        $shop_name = $this->getPost('shop_name');

        if (!$shop_name) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家名称 未填写'));
        }
        $shop_exit = $this->_getPlayShopTable()->get(array('shop_name' => $shop_name));
        if ($shop_exit && !$shop_id) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '该店铺名称已经存在'));
        }

        if (!$shop_phone) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家电话 未填写'));
        }

        if (!$shop_address) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家地址 未填写'));
        }

        if (!$addr_x || !$addr_y) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家坐标 未填写'));
        }

        if (!$busniess_circle) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '商家商圈 未填写'));
        }

        if ($shop_open >= $shop_close) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '开店 关店 时间不合理'));
        }

        $marketData = $this->_getPlayMarketTable()->get(array('market_id' => $market_id));

        $data = array(
            'shop_mid' => $marketData->market_id,
            'shop_type' => 0, //todo 待定
            'shop_city' => $marketData->market_city,
            'shop_address' => $shop_address,
            'addr_x' => $addr_x,
            'addr_y' => $addr_y,
            'shop_phone' => $shop_phone,
            'shop_open' => $shop_open,
            'shop_close' => $shop_close,
            'shop_name' => $shop_name,
            'busniess_circle' => $busniess_circle,
        );

        if ($shop_id) {
            $status = $this->_getPlayShopTable()->update($data, array('shop_id' => $shop_id));
        } else {
            $validator = new Validation();
            $shop_pass = $validator->rand_gen_code();
            $data['password'] = $shop_pass;
            $status = $this->_getPlayShopTable()->insert($data);
        }
        if ($status) {
            // todo 产生一个账号；
            if ($shop_id) {
                $this->_getPlayAdminTable()->update(array('admin_name' => $shop_name), array('shop_id' => $shop_id));
            } else {
                $shop_id = $this->_getPlayShopTable()->getlastInsertValue();
                $this->_getPlayAdminTable()->insert(array('admin_name' => $shop_name, 'password' => md5($shop_pass), 'shop_id' => $shop_id, 'group' => 2, 'dateline' => time(), 'status' => 1));
            }
            return $this->jsonResponsePage(array('status' => 1));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }

    }

    public function saveMarketAction()
    {
        $market_id = (int)$this->getPost('market_id');
        $market_name = $this->getPost('market_name');
        $market_type = $this->getPost('market_type');
        $market_city = 'WH'; //todo 城市名

        //todo 字段验证
        if (!$market_name) {
            return $this->_Goto('商家名称 未填写');
        }

        $data = array(
            'market_name' => $market_name,
            'market_type' => $market_type,
            'market_city' => $market_city,
        );
        if ($market_id) {
            $status = $this->_getPlayMarketTable()->update($data, array('market_id' => $market_id));
        } else {
            $status = $this->_getPlayMarketTable()->insert($data);
        }
        if ($status) {
            if ($market_id) {
                return $this->_Goto('保存成功');
            } else {
                $mid = $this->_getPlayMarketTable()->getlastInsertValue();
                return $this->_Goto('保存成功', "/wftadlogin/seller/market?id=$mid");
            }
        } else {
            return $this->_Goto('保存失败');
        }

    }

    public function deleteShopAction()
    {
        //todo  根据订单情况 删除登陆账号
        $shopId = (int)$this->getQuery('sid');
        //关闭店铺
        $status = $this->_getPlayShopTable()->update(array('shop_status' => -1), array('shop_id' => $shopId));
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, 'message' => '关闭店铺成功',));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '关闭店铺失败',));
        }
    }

    public function deleteMarketAction()
    {
        //todo  根据订单情况 删除登陆账号
        $mId = (int)$this->getQuery('mid');
        $this->_getPlayMarketTable()->update(array('market_status' => -1), array('market_id' => $mId));
        $this->_getPlayShopTable()->update(array('shop_status' => -1), array('shop_mid' => $mId));
        return $this->jsonResponsePage(array('status' => 1, 'message' => '关闭商家成功',));
    }

    public function setmapAction()
    {
        $vm = new ViewModel();
        $vm->setTerminal(true);
        return $vm;
    }

}
