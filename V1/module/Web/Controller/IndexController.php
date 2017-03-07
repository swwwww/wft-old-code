<?php

namespace Web\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\ShopCache;
use Deyi\JsonResponse;
use Deyi\Mcrypt;
use library\Service\System\Cache\RedCache;
use Deyi\Upload;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    use JsonResponse;
    use BaseController;

    
    //todo 解密分享的id
    public function getShareData()
    {
        if (!isset($_GET['p']) or !$_GET['p']) {
            return false;
        }
        $p = preg_replace(array('/-/', '/_/'), array('+', '/'), $_GET['p']);
        $encryption = new Mcrypt();
        $data = $encryption->decrypt($p);
        return json_decode($data, 1);  //返回对象数组  uid and timestamp
    }

    public function tuiAction()
    {

    }

    public function indexAction()
    {

        return array();
        var_dump($_SERVER['REQUEST_URI']);
        var_dump($_GET['p']);

        var_dump($this->getShareData());


        $HTML = <<<HTML
<html>
<head>
<title>hello</title>
<meta charset="utf-8">
<script type="text/javascript" src="/js/jquery.min.js"></script>
</head>

<body>
传递的值：
<pre>
{$data}
</pre>

<input type="button" class="share" value="分享当前页 app=0" v="0"><br><br>
<input type="button" class="share" value="分享当前页 app=1" v="1"><br><br>
<input type="button" class="share" value="分享当前页 app=2" v="2"><br><br>
<input type="button" class="share" value="分享当前页 app=3" v="3"><br><br>
<input type="button" class="share" value="分享当前页 app=4" v="4"><br><br>


<input type="button" id="shua" value="启动app">
<script>


$(function(){

    url=encodeURI('http://10.0.18.19:90/web/index/index?id=123');
    $('.share').click(function(){

   var title='测试标题';
   var img='http://10.0.18.19:90/uploads/2015/05/13/bf80139aae231dea085c95b5a3d20ae5.jpg';
   var content='内容内容内容内容内容内容内容内容内容内容内容'
   var app=$(this).attr('v');
   alert(app);
        if (navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iphone os/i) || navigator.userAgent.match(/ipod/i)) {
                window.location.href = 'webshare$\$app='+app+'&title='+title+'&url='+url+'&img='+img+'&content='+content;
            } else if (navigator.userAgent.match(/android/i)) {
                window.getdata.webShare(app,url,title,content,img);
            } else{
               alert('不是手机浏览器')
            }
    });


    $('#shua').click(function(){
            if (navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iphone os/i) || navigator.userAgent.match(/ipod/i)) {
              setTimeout("window.location.href = 'http://www.baidu.com';",500)
             window.location.href = 'woshixiaoyan://web?url='+url;

            } else if (navigator.userAgent.match(/android/i)) {
             setTimeout("window.location.href = 'http://www.baidu.com';",500)
             window.location.href = 'wanfantian://com.deyi.wanfantian?url='+url;

            } else{

             setTimeout("window.location.href = 'http://www.baidu.com';",500)
            window.location.href = 'wanfantian://com.deyi.wanfantian?url='+url;

            alert('不是手机浏览器')
            }
    })
})
</script>
</body>
</html>
HTML;


        echo $HTML;
        exit;
    }


    public function downloadAction()
    {
        $vm = new viewModel(array());
        $vm->setTerminal(true);
        return $vm;
    }

    //用户须知
    public function statementAction()
    {
        $vm = new viewModel(array());
        $vm->setTerminal(true);
        return $vm;
    }

    //免责声明
    public function exemptionAction()
    {
        $vm = new viewModel(array());
        $vm->setTerminal(true);
        return $vm;
    }


    //统计点击数 临时
    public function clickcountAction()
    {

        $this->_getPlayClickLogTable()->clickRecord('download', 1);
        header("Location: http://wan.deyi.com/app/index.php?ac=2");  //活动包
        exit;

    }

    //日志自动监控配置文件生成
    public function selectLogSetAction()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : 0;
        $time = isset($_GET['time']) ? (int)$_GET['time'] : 0;
        $file_name = $_SERVER['DOCUMENT_ROOT'] . '/../Script/set.lock';
        if ($action == 'set') {
            file_put_contents($file_name, $time);
            echo 'set_ok ';
        }
        exit;
    }

    public function clearcockieAction()
    {

        foreach ($_COOKIE as $k=>$v) {
            setcookie($k, "", time() - 10,'/');
        }

        echo '成功';exit;
    }

}
