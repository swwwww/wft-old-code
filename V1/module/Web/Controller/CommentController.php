<?php
namespace Web\Controller;

use Deyi\Account\Account;
use Deyi\BaseController;
use Deyi\Coupon\Coupon;
use Deyi\Integral\Integral;
use Deyi\JsonResponse;
use Deyi\Social\SendSocialMessage;
use Zend\Db\Sql\Expression;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class CommentController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;
    use SendSocialMessage;

    public function indexAction()
    {

    }

    public function detailAction()
    {

    }

    public function reCommentAction()
    {
        // todo 获取评论详情
        $object_id = $this->getQuery('id'); // 对象id　活动为场次id
        $type = $this->getQuery('type'); // 类别
        $pid = $this->getQuery('pid'); // 评论id

        $url = $this->_getConfig()['url'] . '/post/index/info';
        $post_arr = array('uid' => $_COOKIE['uid'], 'pid' => $pid, 'pagenum' => 10);

        $comment_json = $this->post_curl($url, $post_arr, '', $_COOKIE);
        $comment = json_decode($comment_json, true);

        $comment = $comment['response_params'];

        $comment['score'] = round(($comment['score'] / 5) * 100) . '%';
        $comment['dateline'] = Date("Y年m月d日 H:i:s", $comment['dateline']);
        $vm = new ViewModel([
            'comment'   => json_encode($comment),
            'pid'       => $pid,
            'type'      => $type,
            'object_id' => $object_id

        ]);
        $vm->setTerminal(true);
        return $vm;
    }


}
