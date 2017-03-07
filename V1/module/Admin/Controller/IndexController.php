<?php

namespace Admin\Controller;

use Deyi\Alipay\Alipay;
use Deyi\ImageProcessing;
use Deyi\JsonResponse;
use Deyi\OutPut;
use Deyi\Paginator;
use Deyi\WeiSdkPay\WeiPay;
use Deyi\WeiXinFun;
use Deyi\WeiXinPay\WeiXinPayFun;
use library\Fun\Common;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends BasisController
{
    use JsonResponse;
    

    public function indexAction()
    {

        /*if ($_COOKIE['group'] == 4) {
            header('Location: /wftadlogin/code/trade');
            exit;
        }
        $data = $this->_getPlayShopTable()->fetchAll(array('shop_status' => 0));
        return ['data' => $data];*/
    }


    //工具前端
    public function selectAction()
    {
        $data = <<<HTML
            <div style="">
            <form action="/wftadlogin/index/funlist?action=sql" method="post" style="padding: 20px;">
                        请输入select语句
                        <br>
<textarea name="sql" style="width: 700px; height: 100px;"></textarea>
<br>
<button>提交</button>
</form>


<form method="post" action="/wftadlogin/index/funlist?action=downfile">
地址V1..
<input type="text" name="path">
<button>提交</button>
</form>
</p>

<form action="/wftadlogin/index/funlist?action=upfile" method="post"
enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file" /> 
<br>
<br>
<input type="submit" name="tijiao" value="上传" />
</form>

<form action="/wftadlogin/index/funlist?action=checkorder" method="post">
查询第三方订单:<input type="text" name="order_sn" value="" />
<br />
支付类型：<input type="text" name="account_type" placeholder="可以不填" value="" />
<button>提交</button>
</form>


<p>
<a href="/wftadlogin/index/clearcache">清理缓存</a>
</p>
<p>
<a href="/web/index/clearcockie">清理cockie</a>
</p>

<p>
<a href="/wftadlogin/index/funlist?action=getLog">查看日志记录</a>
</p>
<p>



</div>
HTML;
        echo $data;
        exit;
    }

    //工具后端
    public function funlistAction()
    {


        $action = $_GET['action'];

        if (!$action) {
            exit('no action');
        }

        if ($action == 'sql') {
            $sql = isset($_POST['sql']) ? $_POST['sql'] : '';
            if ($sql and stripos($sql, 'select') !== false) {
                $db = $this->_getAdapter();
                try{
                    $data = $db->query($sql, array())->toArray();
                    $count = $db->query($sql, array())->count();
                }catch (\Exception $e){
                    echo $e->getMessage();
                    exit;
                }

                /********* 导出 ********/
                if ($_GET['out']) {
                    $head = array();
                    foreach ($data[0] as $k => $v) {
                        $head[] = $k;
                    }
                    $out = new OutPut();
                    $file_name = date('Y-m-d H:i:s', time()) . '.csv';
                    $out->out($file_name, $head, $data);
                    exit;
                }
                /********* 导出 ********/

                /********** 显示查询结果 ***********/
                echo "SQL: ", $sql, "<br>";
                echo 'COUNT: ', $count, "\r\n";
                echo '<pre style="padding: 20px;">';
                foreach ($data as $v) {
                    print_r($v);
                    echo '<hr>';
                }
                echo '</pre>';
                exit;
                /********** 显示查询结果 ***********/
            }
        }

        if ($action == 'upfile') {
            if (!empty($_FILES)) {
                $file = $_FILES['file'];
                if ($file['type'] !== 'application/gzip') {
                    exit('文件类型错误');
                }
                if (substr($file["name"], 0, 3) !== 'new') {
                    exit('不属于本项目,请用new打头');
                }
                //临时存放目录
                $newfiledir = $_SERVER['DOCUMENT_ROOT'] . "/../log/" . $file["name"];
                //移动文件
                move_uploaded_file($file["tmp_name"], $newfiledir);
                
                try{
                    $phar = new \PharData($newfiledir);
                    $res = $phar->extractTo($_SERVER['DOCUMENT_ROOT'] . '/../', null, true);
                }catch (\Exception $e){
                    //删除临时文件
                    unlink($newfiledir);
                    echo $e->getMessage();
                    exit;
                }




                //删除临时文件
                unlink($newfiledir);
                if ($res) {
                    exit('上传成功,文件大小:' . ($file['size'] / 1024) . 'KB');
                } else {
                    exit('上传失败');
                }
            }
        }

        if ($action == 'downfile') {
            //下载文件
            $_GET['path'] = trim($_POST['path']);
            $path = $_SERVER['DOCUMENT_ROOT'] . "/../" . $_GET['path'];

            header('Content-Description: File Transfer', true);
            header('Content-Type: application/octet-stream', true);
            header('Content-Disposition: attachment; filename=' . basename($path), true);
            header('Content-Transfer-Encoding: binary', true);
            header('Expires: 0', true);
            header('Cache-Control: must-revalidate, post-check=0,pre-check=0', true);
            header('Pragma:public', true);
            header('Content-Length:' . filesize($path), true);
            readfile($path);
        }

        if ($action == 'getLog') {
            //下载文件


            echo "<pre>";
            echo WriteLog::getLogLastLines();
            echo "</pre>";
            exit;
        }

        if ($action == 'checkorder') {

            $order_sn = $this->getPost('order_sn', '');
            $account_type = $this->getPost('account_type', '');

            $order_sn = (int)preg_replace('|[a-zA-Z/]+|','',$order_sn);

            if ($account_type) {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn, 'account_type' => $account_type));
            } else {
                $orderData = $this->_getPlayOrderInfoTable()->get(array('order_sn' => $order_sn));
            }

            if (!$orderData) {
                $this->_Goto('该订单不存在');
            }

            if ($orderData->account_type == 'weixin') {
                $Wei = new WeiPay();
                $result = $Wei->getOrderInfo($orderData->trade_no);
                var_dump($result);
            } elseif ($orderData->account_type == 'new_jsapi') {
                $WeiXinWap = new WeiXinPayFun($this->_getConfig()['wanfantian_weixin']);
                $result = $WeiXinWap->getOrderInfo($orderData->trade_no);
                var_dump($result);
            } elseif ($orderData->account_type == 'alipay') {
                $alipay = new Alipay();
                $result = $alipay->getOrderInfo('WFT'.$orderData->order_sn);
                var_dump($result);
            }

            exit;

        }

        exit();
    }

    public function phonecodeAction()
    {

        $status = $_GET['status'];
        $phone = $_GET['phone'];
        $page = $_GET['p'];
        $pagesum = 20;

        $where = array();

        if ($status !== null) {
            $where['status'] = $status;
        }

        if ($phone) {
            $where['phone'] = $phone;
        }

        if (!$page) {
            $page = 1;
        }

        $start = ($page - 1) * $pagesum;

        $data = $this->_getPlayAuthCodeTable()->fetchLimit($start, $pagesum, array(), $where, array('id' => 'desc'));


        //获得总数量
        $count = $this->_getPlayAuthCodeTable()->fetchCount($where);
        //创建分页
        $url = '/wftadlogin/index/phonecode';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml()
        );


    }

    public function clearcacheAction()
    {
        RedCache::clearAll();
        return $this->_Goto('清除成功！', "javascript:location.href = document.referrer");

    }

    public function logAction()
    {
        // echo '<a href="/wftadlogin/index/deletelog">清空日志</a><br>';
        $pay_status = $this->_getConfig()['order_status'];
        $data = $this->_getPlayOrderActionTable()->fetchAll(array(), array('action_id' => 'desc'), 100);

        foreach ($data as $v) {
            echo "订单单号:{$v->order_id}<br>";
            echo "订单状态:{$pay_status[$v->play_status]}<br>";
            echo "操作说明:{$v->action_note}<br>";
            $time = date('Y-m-d H:i:s', $v->dateline);
            echo "操作时间:{$time}<br>";
            echo "操作人员:{$v->action_user}\r\n";
            echo "<hr>";
        }
        exit;
    }


    public function loginAction()
    {
        $user = $this->getPost('username');
        $pwd = $this->getPost('pwd');
        $city = $this->getPost('city');
        $pwd = md5($pwd);

        if ($user and $pwd) {

            $data = $this->_getPlayAdminTable()->fetchAll(array('admin_name' => $user, 'admin_city' => (string)$city))->current();

            $password = md5($pwd . $data->salt);

            if ($data && $data->password === $password) {
                if ((int)$data->status === 0 or (int)$data->is_closed===1) {
                    exit("<h1>账号已禁用</h1>");
                }

                //生成验证信息
                $hash_data = json_encode(array('user' => (string)$user, 'id' => (int)$data->id, 'group' => (int)$data->group, 'city' => (string)$city));
                $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);

                $domain=null;

                if(Common::isUp()){
                    $domain='.wanfantian.com';
                }
                setcookie('user', $user, time() + 86400, '/',$domain);
                setcookie('token', $token, time() + 86400, '/',$domain);
                setcookie('group', $data->group, time() + 86400, '/',$domain);
                setcookie('id', $data->id, time() + 86400, '/',$domain);
                setcookie('city', (string)$city, time() + 86400, '/',$domain);

                exit('<script> window.location.href="/wftadlogin/";</script>');
            }

        }

        $v = new ViewModel(array(
            'city' => $this->getAllCities(1),
        ));
        $v->setTerminal(true);
        return $v;
    }

    //上传图片接口
    public function uploadAction()
    {
        // $is_w = (int)$this->params()->fromPost('is_w', 0);
        if ($this->getRequest()->isPost()) {

            //上传图片
            $fileMaxSize = 1024;
            $imgMaxWidth = 1000;
            $imgMaxHeight = 1000;
            $none = 1;
            /*
             * 企业相关  use_id 为企业id
             * 课程相关  use_id 为课程id
             *
             * publicityimg 商户宣传图片
             * event        活动图片
             *
             * post         评论图片
             *
             *
             * */

            //此参数全部为可选
            $uid = $this->params()->fromPost('uid', 1);
            $type = $this->params()->fromPost('type', null);
            $use_id = $this->params()->fromPost('use_id', null);
            $remote = $this->params()->fromPost('remote', 0); //是否远程图片
            $editor_id = $this->getQuery('editorid'); //编辑器id

            if ($editor_id == 'myEditorVir') {//虚拟评论不限制用户评论
                $none = 0;
            }

            if ($this->getQuery('boot') == 'out') {
                $imgMaxHeight = 2000;
            }

            if ($this->getQuery('boot') == 'outer') {
                $imgMaxHeight = 2001;
                $imgMaxWidth = 2670;
                $fileMaxSize = 2048;
            }

            if ($this->getQuery('type') == 'contract') {
                $imgMaxHeight = 2000;
                $imgMaxWidth = 2000;
            }


            $uploader = new Upload($_FILES['file']);
            if ($none) {
                if (!$uploader->isImage()) {
                    return $this->jsonResponse(array('status' => 0, 'message' => "请选择jpg、png、gif图片格式"));
                } elseif (!($uploader->getSize() < $fileMaxSize)) {
                    return $this->jsonResponse(array('status' => 0, 'message' => "请选择文件大小{$fileMaxSize}k以内的图片"));
                } elseif ($uploader->getHeight() > $imgMaxHeight || $uploader->getWidth() > $imgMaxWidth) {
                    return $this->jsonResponse(array('status' => 0, 'message' => "请选择{$imgMaxWidth} X {$imgMaxHeight}像素内的图片"));
                } elseif (($this->getQuery('type') == 'float_img')) {
                   // return $this->jsonResponsePage(array('status' => 0, 'message' => "图片大小不正确 请上传64*64px的图片"));
                }
            }


            $file = $uploader->save();
            //插入数据
            if ($type and $use_id) {
                $status = $this->_getPlayAttachTable()->insert(array('uid' => $uid, 'use_id' => $use_id, 'name' => $uploader->getname(), 'use_type' => $type, 'dateline' => time(), 'url' => $file, 'is_remote' => $remote));
            }
            if ($type == 'post') {
                //生成矩形缩略图
                $up_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $file);
                $up_img->MaxSquareZoomResizeImage(320)->Save($_SERVER['DOCUMENT_ROOT'] . $file . '.thumb.jpg');

                $post_data = $this->_getPlayPostTable()->get(array('pid' => $use_id));

                $photo_list = json_decode($post_data->photo_list, true);
                $photo_list[] = $file;
                $this->_getPlayPostTable()->update(array('photo_number' => new Expression('photo_number+1'), 'photo_list' => json_encode($photo_list)), array('pid' => $use_id));

            }

            if ($editor_id == 'myEditorThumb') { //圈子发言 缩略图
                $up_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $file);
                $res = $up_img->MaxSquareZoomResizeImage(320)->Save($_SERVER['DOCUMENT_ROOT'] . $file . '.thumb.jpg');

                if ($res !== true) {
                    return $this->jsonResponse(array('status' => 0, 'message' => "缩略图未生成"));
                    exit;
                }
            }

            if ($editor_id == 'myEditorVir') {
                $thumb_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $file);
                $up_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $file);
                $res = $thumb_img->MaxWidthResizeImage(600)->Save($_SERVER['DOCUMENT_ROOT'] . $file);
                //兼容android 老版本 生成320缩略图
                $up_img->MaxSquareZoomResizeImage(320)->Save($_SERVER['DOCUMENT_ROOT'] . $file . '.thumb.jpg');

                if ($res !== true) {
                    return $this->jsonResponse(array('status' => 0, 'message' => "失败"));
                }
            }

            echo json_encode(
                array('status' => 1,
                    'url' => $file,
                    'name' => $uploader->getname(),
                    'originalName' => $uploader->getname(),
                    'message' => "上传成功",
                    'title' => $uploader->getFileName(),
                    'original' => $uploader->getFileName(),
                    'state' => 'SUCCESS',
                    'size' => $uploader->getSize(),
                    'type' => '.' . $uploader->getFileExtName()
                ));
            exit;
        } else {
            exit('访问错误');
        }

    }

    public function deleteimgAction()
    {
        $id = $this->params()->fromQuery('id', null);
        if (!$id) {
            return $this->_Goto('参数错误');
        }
        $status = $this->_getEduAttachTable()->delete(array('id' => $id));
        if ($status) {
            return $this->_Goto('删除成功', "javascript:location.href = document.referrer");
        } else {
            return $this->_Goto('删除失败');
        }
    }

    public function logoutAction()
    {
        $domain=null;
        if(Common::isUp()){
            $domain='.wanfantian.com';
        }


        setcookie('user', '', time() - 10, '/',$domain);
        setcookie('token', '', time() - 10, '/',$domain);
        setcookie('group', '', time() - 10, '/',$domain);
        setcookie('id', '', time() - 10, '/',$domain);
        header('Location: /wftadlogin');
        exit;
    }

    //发送提醒短信记录
    public function messageAction()
    {


        $page = $_GET['p'];
        $pagesum = 20;

        $where = array();


        if (!$page) {
            $page = 1;
        }

        $start = ($page - 1) * $pagesum;

        $data = $this->_getPlayMessageLogTable()->fetchLimit($start, $pagesum, array(), array(), array('id' => 'desc'));

        //获得总数量
        $count = $this->_getPlayMessageLogTable()->fetchCount();
        //创建分页
        $url = '/wftadlogin/index/message';
        $paginator = new Paginator($page, $count, $pagesum, $url);
        return array(
            'data' => $data,
            'pagedata' => $paginator->getHtml()
        );
    }

    //获取指定类型数据分享数据
    public function sharedataAction()
    {
        $type = $this->getQuery('type', 'coupon');
        $id = $this->getQuery('id', 0);
        echo (int)$this->_getPlayShareTable()->fetchCount(array('type' => $type, 'share_id' => $id));
        exit;
    }

    //智能公交下载点击数
    public function downloadcountAction()
    {
        $data = $this->_getPlayClickLogTable()->get(array('object_id' => 1, 'object_type' => 'download'));
        echo "click number :{$data->click_number}\n";
        exit;
    }
}
