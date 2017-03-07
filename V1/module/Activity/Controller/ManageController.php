<?php

namespace Activity\Controller;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\WriteLog;
use Zend\Db\Sql\Expression;
use Zend\Mvc\Controller\AbstractActionController;
use Deyi\SendMessage;
use Zend\View\Model\ViewModel;
use Deyi\Paginator;
use Deyi\OutPut;
use Deyi\Upload;
use Zend\EventManager\EventManagerInterface;

class ManageController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;


    public function __construct()
    {

    }

    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $controller = $this;
        $events->attach('dispatch', function ($e) use ($controller) {
            if ($_SERVER['REQUEST_URI'] != '/activity/manage/login') { //排除登陆界面
                if (isset($_COOKIE['user'])) {
                    $hash_data = json_encode(array('user' => $_COOKIE['user'], 'id' => $_COOKIE['id'], 'group' => $_COOKIE['group']));
                    $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);
                    if ($token == $_COOKIE['token']) {
                    } else {
                        header('Location: /activity/manage/login');
                        exit;
                    }
                } else {
                    header('Location: /activity/manage/login');
                    exit;
                }
            }

        }, 100);
    }

    //H5 wap web 活动入口
    public function indexAction() {
        $v = new ViewModel();
       
        return $v;
    }

    public function loginAction() {
        $user = $this->getPost('username');
        $pwd = $this->getPost('pwd');
        $pwd = md5($pwd);
        if ($user and $pwd) {
            $data = $this->_getPlayAdminTable()->get(array('admin_name' => $user));

            $password = md5($pwd . $data->salt);

            if ($data && $data->password === $password) {
                if ($data->status == 0) {
                    exit("<h1>账号已禁用</h1>");
                }

                //生成验证信息
                $hash_data = json_encode(array('user' => $user, 'id' => $data->id, 'group' => $data->group));
                $token = hash_hmac('sha1', $hash_data, $this->_getConfig()['token_key']);

                setcookie('user', $user, time() + 28800, '/');
                setcookie('token', $token, time() + 28800, '/');
                setcookie('group', $data->group, time() + 28800, '/');
                setcookie('id', $data->id, time() + 28800, '/');
                header('Location: /activity/manage/index');
            }

        }

        $v = new ViewModel();
        $v->setTerminal(true);
        return $v;
    }

    public function logoutAction()
    {
        setcookie('user', '', time() - 10, '/');
        setcookie('token', '', time() - 10, '/');
        setcookie('group', '', time() - 10, '/');
        setcookie('id', '', time() - 10, '/');
        header('Location: /activity/huiju/');
        exit;
    }

    public function uploadAction()
    {
        // $is_w = (int)$this->params()->fromPost('is_w', 0);
        if ($this->getRequest()->isPost()) {
            //上传图片
            $fileMaxSize = 1024;
            $imgMaxWidth = 1000;
            $imgMaxHeight = 1000;
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

            if ($this->getQuery('boot') == 'out') {
                $imgMaxHeight = 2000;
            }

            if ($this->getQuery('type') == 'contract') {
                $imgMaxHeight = 2000;
                $imgMaxWidth = 2000;
            }


            $uploader = new Upload($_FILES['file']);
            if (!$uploader->isImage()) {
                return $this->jsonResponse(array('status' => 0, 'message' => "请选择jpg、png、gif图片格式"));
            } elseif (!($uploader->getSize() < $fileMaxSize)) {
                return $this->jsonResponse(array('status' => 0, 'message' => "请选择文件大小{$fileMaxSize}k以内的图片"));
            } elseif ($uploader->getHeight() > $imgMaxHeight || $uploader->getWidth() > $imgMaxWidth) {
                return $this->jsonResponse(array('status' => 0, 'message' => "请选择{$imgMaxWidth} X {$imgMaxHeight}像素内的图片"));
            } elseif (($this->getQuery('type') == 'float_img') && ($uploader->getHeight() != 128 || $uploader->getWidth() != 128)) {
                return $this->jsonResponsePage(array('status' => 0, 'message' => "图片大小不正确 请上传64*64px的图片"));
            } else {


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

                if ($this->getQuery('editorid') == 'myEditorThumb') { //圈子发言 缩略图
                    $up_img = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $file);
                    $res = $up_img->MaxSquareZoomResizeImage(320)->Save($_SERVER['DOCUMENT_ROOT'] . $file . '.thumb.jpg');

                    if ($res !== true) {
                        return $this->jsonResponse(array('status' => 0, 'message' => "缩略图未生成"));
                        exit;
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
            }
            return $this->jsonResponsePage(array('status' => 0, 'message' => "请选择图片上传"));
        } else {
            echo 67;
            exit('访问错误');
        }

    }





}
