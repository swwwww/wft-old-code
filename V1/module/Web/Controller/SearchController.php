<?php
namespace Web\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\GoodCache;
use Deyi\GetCacheData\PlaceCache;
use Deyi\JsonResponse;
use library\Service\System\Cache\RedCache;
use Deyi\WeiXinFun;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class SearchController extends AbstractActionController{
    use JsonResponse;
    use BaseController;

    public function indexAction()
    {
        $url = $this->_getConfig()['url'] . "/web/search/index";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
        $history = unserialize($_COOKIE['history']);
        $city = $this->getQuery("city");
        $vm = new ViewModel([
            'history'=>$history,
            'city'=>$city,
            'authorUrl'=>$toUrl
        ]);
        $vm->setTerminal(true);
        return $vm;

    }

    //�������
    public function infoAction(){
        $url = $this->_getConfig()['url'] . "/web/search/info";
        $weixin = new WeiXinFun($this->getwxConfig());
        $toUrl = $weixin->getAuthorUrl($url, 'snsapi_userinfo');
        $keyword = trim($this->getQuery('keyword'));
        $this->history(array($keyword));
        $city = $this->getQuery("city");
        $vm = new ViewModel([
            'city'=>$city,
            'authorUrl'=>$toUrl
        ]);
        $vm->setTerminal(true);
        return $vm;
    }



    /**
     * @param $sql
     * @return Result;
     */
    function query($sql)
    {
        $db = $this->getServiceLocator()->get('Zend\Db\Adapter\Adapter');
        $stmt = $db->query($sql);
        $result = $stmt->execute($stmt);
        return $result;
    }

    /**
     * ��Ʒ��ʷ�����¼
     * $data ��Ʒ��¼��Ϣ
     */
    private function history($data)
    {
        if(!$data)
        {
            return false;
        }

        //�ж�cookie�������Ƿ��������¼
        if(isset($_COOKIE['history']))
        {
            $history = unserialize($_COOKIE['history']);
            array_unshift($history, $data); //�������¼��������

            /* ȥ���ظ���¼ */
            $rows = array();
            foreach ($history as $v)
            {
                if(in_array($v, $rows))
                {
                    continue;
                }
                $rows[] = $v;
            }

            /* �����¼��������5��ȥ�� */
            while (count($rows) > 10)
            {
                array_pop($rows); //����
            }

            setcookie('history',serialize($rows),time() + 3600 * 24 * 30,'/');
        }
        else
        {
            $history = serialize(array($data));

            setcookie('history',$history,time() + 3600 * 24 * 30,'/');
        }
    }

    public function delAction(){
        setcookie('history','',-1,'/');
        return $this->jsonResponse(array('status'=>1));
    }
}
