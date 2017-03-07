<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use Deyi\ImageProcessing;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class NewsController extends AbstractActionController
{
    use JsonResponse;
    //use BaseController;

    //资讯列表
    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $city = $this->getQuery('city', 'WH');
        $like = $this->getQuery('k', '');

        $pagesum = 10;
        $where = array(
            'play_news.status >= ?' => 0,
            'news_city = ?' => $city,
        );
        if ($like) {
            $where['title like ?'] = '%'.$like.'%';
        }
        $start = ($page - 1) * $pagesum;
        $order = array('dateline' => 'desc');
        $data =  $this->_getPlayNewsTable()->getAdminNewsList($start, $pagesum, array(), $where, $order);
        //获得总数量
        $count = $this->_getPlayNewsTable()->getAdminNewsList(0, 0, array(), $where, $order)->count();
        //创建分页
        $url = '/wftadlogin/news';
        $paginator = new Paginator($page, $count, $pagesum, $url);

        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml(),
            'cityData' => $this->_getConfig()['city'],
            'city' => $city,
        );
    }


    public function newAction() {
        $nid = (int)$this->getQuery('nid');
        $city = $this->getQuery('city');
        $data = array();

        $sData = array();
        $shopData = '';
        $mData = '';
        if ($nid) {
            $data = $this->_getPlayNewsTable()->get(array('id' => $nid));
            $city = $data->news_city;
            $flag = json_decode($data->address);
            if ($flag->type == 2) {
                $shopData = $this->_getPlayShopTable()->fetchAll(array('shop_mid' => $flag->mid, 'shop_status' => 0));
                $mData = $this->_getPlayMarketTable()->get(array('market_id' => $flag->mid));
                foreach ($flag->mes as $k => $l) {
                    $sData[$l] = $k;
                }
            }
        }

        $rdata = $this->_getPlayRegionTable()->fetchAll(array(), array('rid' => 'asc'))->toArray();
        $body = array();
        $cData = array();
        foreach ($rdata as $v) {
            $cData[$v['rid']] = $v['name'];
            $id = substr((string)$v['rid'], 0, -2);
            if (substr((string)$v['rid'], -2, 2) == '00') {
                $head[$id] = $v;
            }
            $body[$id][] = $v;
        }
        foreach ($body as $k => $v) {
            unset($body[$k][0]);
        }



        if (!in_array($city, array_flip($this->_getConfig()['city']))) {
            return $this->_Goto('非法操作');
        }

        $vm = new ViewModel(
            array(
                'data' => $data,
                'city' => array(
                    'mark' => $city,
                    'name' => $this->_getConfig()['city'][$city],
                ),
                'sData' => $sData,
                'mData' => $mData,
                'shopData' => $shopData,
                'select_circle' => array($head, $body),
                'cData' => $cData,
            )
        );
        return $vm;

    }

    public function saveAction() {

        // todo 字段限制 图片处理
        $title = $this->getPost('title');
        $editor_word = $this->getPost('editor_word');
        $information = $this->getPost('editorValue');
        $reference_price = $this->getPost('reference_price') ? $this->getPost('reference_price') : 0;
        $age_min = $this->getPost('age_min');
        $age_max = $this->getPost('age_max');
        $news_city = $this->getPost('news_city');
        $allow_post = $this->getPost('allow_post');

        $cover = $this->getPost('cover');
        if (!$cover) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '封面图片'));
        }

        $cover_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $cover);

        $cover_status = $cover_class->scaleResizeImage(720,360);
        if ($cover_status) {
            $cover_status->save($_SERVER['DOCUMENT_ROOT'] . $cover);
        }

        $surface_plot = $this->getPost('surface_plot') ? $this->getPost('surface_plot') : $this->getPost('cover'). '.min.jpg';
        $surface_per = $this->getPost('surface_plot') ? $this->getPost('surface_plot') : $cover;
        $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $surface_per);

        $surface_status = $surface_class->scaleResizeImage(360,360);

        if ($surface_status) {
            $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $surface_plot);
        }

        if (!$title) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '资讯标题'));
        }
        if (!$editor_word) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '小编说'));
        }
        if (!$information) {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '图文详情'));
        }


        $aType = $this->getPost('aType');
        if ($aType == 1) {
            $addr_x = $this->getPost('addr_x');
            $addr_y = $this->getPost('addr_y');
            $address = $this->getPost('address');
            $circle = $this->getPost('circle');
            if (!$addr_x || !$addr_y || !$address || !$circle) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '地址详情不正确'));
            }
            $addr = json_encode(array(
                'type' => $aType,
                'mes' => array(
                    'x' => $addr_x,
                    'y' => $addr_y,
                    'address' => $address,
                    'circle' => $circle,
                ),
            ));
        } elseif ($aType == 2) {
            $mid = $this->getPost('mid');
            $mes = $this->params()->fromPost('shopIds');
            if (!count($mes) || !$mid) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => '地址详情不正确'));
            }

            foreach ($mes as $k=>$l){
                $shopData = $this->_getPlayShopTable()->get(array('shop_id' => $k));
                $adr[] = array(
                    'shop_name' => $shopData->shop_name,
                    'shop_address' => $shopData->shop_address,
                    'shop_phone' => $shopData->shop_phone,
                    'addr_x' => $shopData->addr_x,
                    'addr_y' => $shopData->addr_y,
                    'shop_open' => $shopData->shop_open,
                    'shop_close' => $shopData->shop_close,
                );
            }
            $addr = json_encode(array(
                'type' => $aType,
                'mid' => $mid,
                'mes' => $mes,
                'adr' => $adr,
            ));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $data = array(
            'title'  => $title,
            'editor_word' => $editor_word,
            'information' => $information,
            'reference_price' => $reference_price,
            'age_min' => $age_min,
            'age_max' => $age_max,
            'news_city' => $news_city,
            'cover' => $cover,
            'surface_plot'    => $surface_plot,
            'dateline' => time(),
            'editor_id' => $_COOKIE['id'],
            'address' => $addr,
            'allow_post' => $allow_post,
        );
        $nid = (int)$this->getPost('nid');

        if ($nid) {
            $status = $this->_getPlayNewsTable()->update($data, array('id' => $nid));
        } else {
            $status = $this->_getPlayNewsTable()->insert($data);
            $nid = $this->_getPlayNewsTable()->getlastInsertValue();
        }
        if ($status) {
            $this->_getPlayRegionLinkerTable()->delete(array('link_id' => $nid, 'type' => 2));
            if($aType == 1) {
                $this->_getPlayRegionLinkerTable()->insert(array(
                    'link_id' => $nid,
                    'type' => 2,
                    'region_id' => $circle,
                ));
            } else {
                foreach ($mes as $m => $n) {
                    $s = $this->_getPlayShopTable()->get(array('shop_id' => $m))->busniess_circle;
                    $this->_getPlayRegionLinkerTable()->insert(array(
                        'link_id' => $nid,
                        'type' => 2,
                        'region_id' => $s,
                    ));
                }
            }
        }
        if ($status) {
            return $this->jsonResponsePage(array('status' => 1, '成功'));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'message' => '保存失败'));
        }
    }

    public function updateAction() {
        $type = $this->getQuery('type');
        $nid = (int)$this->getQuery('nid');
        $stu = $this->_getPlayNewsTable()->get(array('id' => $nid));
        if (!$stu || $stu->status == -1) {
            return $this->_Goto('非法操作');
        }
        $sta = $stu->status;
        if ($type == 1 && $sta == 1) { //取消发布操作
            $this->_getPlayNewsTable()->update(array('status' => 0), array('id' => $nid));
            return $this->_Goto('ok');
        } elseif ($type == 2 && $sta == 0) { //发布操作
            $this->_getPlayNewsTable()->update(array('status' => 1), array('id' => $nid));
            return $this->_Goto('ok');
        } elseif ($type == 3) { //删除操作
            $this->_getPlayNewsTable()->update(array('status' => -1), array('id' => $nid));
            return $this->_Goto('ok');
        } else {
            return $this->_Goto('非法操作');
        }
    }

    public function getMarketAction() {
        $k = $this->getQuery('k');

        if ($k) {
            $where = array(
                'market_name like ?' => '%'.$k.'%',
            );
            $data = $this->_getPlayMarketTable()->getMarketList(0, 5, array(), $where, array());
            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'mid' => $val->market_id,
                        'mn' => $val-> market_name,
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 0, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }


    }

    public function returnShopAction() {
        $mid = (int)$this->getQuery('mid');
        if ($mid) {
            $data = $this->_getPlayShopTable()->fetchAll(array('shop_status' => 0, 'shop_mid' => $mid));
            $res = array();
            if ($data->count()) {
                foreach ($data as $val) {
                    $res[] = array(
                        'sid' => $val->shop_id,
                        'sn' => $val-> shop_name,
                    );
                }
            }
            return $this->jsonResponsePage(array('status' => 0, 'data' => $res));
        } else {
            return $this->jsonResponsePage(array('status' => 0, 'data' => array()));
        }
    }

    private function _scaleImage($in, $outfile, $widthmax, $heightmax, $imagex, $imagey)
    {
        if ($imagex/$widthmax >= $imagey/$heightmax) {
            $imagexa = $imagey*$widthmax/$heightmax;
            $imageya = $imagey;
            $start_y=0;
            $start_x = ($imagex - $imagexa) / 2;
        } else {
            $imageya = $imagex*($heightmax/$widthmax);
            $imagexa = $imagex;
            $start_x = 0;
            $start_y = ($imagey - $imageya) / 2;
        }

        $in = imagecreatefromstring($in);
        $tc = imagecreatetruecolor($widthmax, $heightmax); //创建空白图片
        imagecopyresampled($tc, $in, 0, 0,$start_x, $start_y, $widthmax, $heightmax, $imagexa, $imageya);  //copy 图片,重新生成
        $status = imagejpeg($tc, $outfile, 100);
        return $status;
    }

    //等边截取缩放图片
    public function setScaleImage($data)
    {
        if (!is_file($_SERVER['DOCUMENT_ROOT'] . $data)) {
            return false;
        }
        $img_info = getimagesize($_SERVER['DOCUMENT_ROOT'] . $data);
        $new_image_name = $_SERVER['DOCUMENT_ROOT']. $data. '.thumb.jpg';

        if ($img_info[0] == 2*$img_info[1]) {
            if (!is_file($new_image_name)) {
                $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $data), $new_image_name, $img_info[1], $img_info[1], $img_info[0], $img_info[1]);
            }
            return false;
        } elseif ($img_info[0] < 2*$img_info[1]) {
            $img_x = $img_info[0];
            $img_y = 1/2*$img_info[0];
            $s = $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $data), $new_image_name, $img_x, $img_y, $img_info[0], $img_info[1]);
            $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT']. $data), $new_image_name. '.thumb.jpg', $img_y, $img_y, $img_info[0], $img_info[1]);
            return $s;
        } else {
            $img_x = 2*$img_info[1];
            $img_y = $img_info[1];
            $s = $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT'] . $data), $new_image_name, $img_x, $img_y, $img_info[0], $img_info[1]);
            $this->_scaleImage(file_get_contents($_SERVER['DOCUMENT_ROOT']. $data), $new_image_name. '.thumb.jpg', $img_y, $img_y, $img_info[0], $img_info[1]);
            return $s;
        }
    }



}
