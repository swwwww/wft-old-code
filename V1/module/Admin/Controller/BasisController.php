<?php

namespace Admin\Controller;

use Deyi\BaseController;
use Deyi\GetCacheData\CityCache;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\EventManager\EventManagerInterface;

class BasisController extends AbstractActionController
{
    use BaseController;

    protected $changeCity;

    public function __construct()
    {

    }

    /**
     * 事件管理器
     * @param EventManagerInterface $events
     * @return void|\Zend\Mvc\Controller\AbstractController
     */
    public function setEventManager(EventManagerInterface $events)
    {

        parent::setEventManager($events);
        $controller = $this;
        $uri = parse_url($_SERVER['REQUEST_URI']);
        $mvc = explode('/', $uri['path']);

        if(count($mvc)!==4){
            $mvc[3] = 'index';
        }

        if(!array_key_exists(2,$mvc)){
            $mvc[2] = 'index';
        }

        $menu = $this->_getAuthMenuTable()->fetchLimit(0,1,['id','branch'],['module'=>$mvc[1],'url'=>$mvc['2'].'/'.$mvc[3]])->current();

        //添加结点代码，上线可删除
        if($menu){
            //var_dump(['module'=>$mvc[1],'url'=>$mvc['2'].'/'.$mvc[3]]);
        }else{
            $module = $this->_getAuthMenuTable()->fetchLimit(0,1,['id'],['module'=>$mvc[1],'url'=>$mvc['2'].'/index'])->toArray();
            if($module){
                //插入数据
                $data = array(
                    'title' => '未命名操作',
                    'pid' => (int)$module[0]['id'],
                    'sort' => 0,
                    'module' => $mvc[1],
                    'url' => $mvc['2'].'/'.$mvc[3],
                    'hide' => 1,
                    'group' => 0,
                    'is_dev' => 0,
                    'branch' => 0,
                    'tip' => '',
                );
                $this->_getAuthMenuTable()->insert($data);
            }else{
                //放到未知
                $data = array(
                    'title' => '未命名操作',
                    'pid' => 501,
                    'sort' => 0,
                    'module' => $mvc[1],
                    'url' => $mvc['2'].'/'.$mvc[3],
                    'hide' => 1,
                    'group' => 0,
                    'is_dev' => 0,
                    'branch' => 0,
                    'tip' => '',
                );
                $this->_getAuthMenuTable()->insert($data);
            }
            $menu = $this->_getAuthMenuTable()->fetchLimit(0,1,['id','branch'],['module'=>$mvc[1],'url'=>$mvc['2'].'/'.$mvc[3]])->current();
        }

        $rules = $this->_getAuthGroupTable()->get(['id' => array_key_exists('group',$_COOKIE)?$_COOKIE['group']:0]);

        if($rules){
            $rule_dis = explode(',',$rules->rules);
        }else{
            $rule_dis = [];
        }

        $menu = iterator_to_array($menu);
        if($menu){
            $current_id = $menu['id'];
        }else{
            //$this->jsonResponsePage(array('status' => 0, 'message' => '非法操作'));
        }

        $c_url = $mvc[2].'/'.$mvc[3];

        if(!array_key_exists('group',$_COOKIE) or $_COOKIE['group'] != 1){
            if(!in_array($current_id,$rule_dis) && $current_id != 306 && $current_id != 309 ){//没有设置结点的情况
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您没有操作权限 id:'.$current_id));
            }

            if($menu and $this->getAdminCity()!=1 && $menu['branch'] == 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您没有城市相应操作权限'));
            }

            if($menu and (!array_key_exists('group',$_COOKIE) or (int)$_COOKIE['group'] !== 2) && array_key_exists('is_dev',$menu) and $menu['is_dev'] == 1){
                return $this->jsonResponsePage(array('status' => 0, 'message' => '您没有开发者操作权限'));
            }
        }

        $events->attach('dispatch', function ($e) use ($controller,$c_url,$rule_dis) {
            $nodes = $this->returnMenu();

            $child = $this->getchildnote($c_url,$nodes);
            $childs = [];
            if($child){
                foreach($child as $c){
                    if($this->isHide($c['hide'],$c['branch'],$c['is_dev'])){
                        continue;
                    }
                    if(!in_array($c['id'],$rule_dis) && $_COOKIE['group']!=1){
                        continue;
                    }
                    $childs[] = $c;
                }
            }

            $controller->layout()->child = $childs;
            $controller->layout()->menu = $nodes;
            $controller->layout()->rule_dis = $rule_dis;

            //弹窗权限
            $alertWindows = array();
            if (in_array(930,$rule_dis)) {//未处理的酒店预约订单
                $alertWindows['orderalert'] = 930;
            }

            if (in_array(993,$rule_dis)) { //未处理咨询处理
                $alertWindows['alertConsult'] = 993;
            }
            $controller->layout()->alert_windows = $alertWindows;


            //$controller->layout()->filtercity = CityCache::getFilterCity($_GET['city']);
            //$top_nav = $this->_getAuthMenuTable()->fetchLimit(0,1,[],['group'=>1])->toArray();
            //$controller->layout()->top_nav = $top_nav;
        }, 100);
    }

    /**
     * 如果是主站使用，返回传参值，没有参数返回空；如果地方站，传参无效
     * @return array|string
     */
    public function chooseCity($list = 1){
        $city = trim($this->getQuery('city', ''));
        if($city && $this->getAdminCity()==1){
            return $city;
        }elseif($this->getAdminCity()!=1){
            return $this->getAdminCity();
        }else{
            if($list){
                return '';
            }else{
                return 'WH';
            }
        }
    }

    //获取后台所在城市
    public function getBackCity()
    {
        return ($_COOKIE['city'] == 1) ? false : $_COOKIE['city'];
    }

    private function isHide($isHide,$isMain,$isDev){
        if($isHide){
            return true;
        }
        if($isMain == 2 && $_COOKIE['city'] == 1 ){
            return true;
        }
        if($isMain == 1 && $_COOKIE['city'] != 1 ){
            return true;
        }

        return ($_COOKIE['group'] != 999 && $isDev == 1);
    }

    private function getchildnote($url,$nodes){
        foreach($nodes as $n){
            if($n['hide']){
            //if($this->isHide($n['hide'],$n['branch'],$n['is_dev'])){
                continue;
            }
            if($n['url'] === $url && (int)$n['pid'] === 0){
                return isset($n['child']) ? $n['child'] : null;
            }

            if(array_key_exists('child',$n) && count($n['child'])>0) {
                foreach ($n['child'] as $c) {
                    if($c['hide']){
                    //if($this->isHide($c['hide'],$c['branch'],$c['is_dev'])){
                        continue;
                    }
                    if ($c['url'] === $url) {
                        return $n['child'];
                    }
                    if (array_key_exists('operator', $c) && count($c['operator']) > 0) {
                        foreach ($c['operator'] as $o) {
                            if($o['hide']){
                            //if($this->isHide($o['hide'],$o['branch'],$o['is_dev'])){
                                continue;
                            }
                            if ($o['url'] === $url) {
                                return $n['child'];
                            }
                        }
                    }

                }
            }

        }
    }

    //权限判断
    private function checkRight($uid) {

        //status 1 正常 0 删除
        //group  1 总管理员 2 店铺  3 编辑 4 财务
        //bind_user_id
        //shop_id
        //admin_city

         //$adminData = $this->_getPlayAdminTable()->get(array('id' => $uid));


         //查询权限 无则写入session

         // 匹配当前URL;
         var_dump(parse_url($_SERVER['REQUEST_URI']));

         return false;


    }



    //操作记录
    public function adminLog($action, $type, $object_id)
    {

        unset($_REQUEST['editorValue']);
        $data = array(
            'dateline' => time(),
            'actor' => $_COOKIE['user'],
            'uid' => $_COOKIE['id'],
            'city' => $_COOKIE['city'],
            'actions' => $action,
            'object_type' => $type,
            'object_id' => $object_id,
            'log' => json_encode($_REQUEST, JSON_UNESCAPED_UNICODE)
        );

        return $this->_getPlayAdminWorkLogTable()->insert($data);

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

    final protected function returnNodes($tree = true){
        static $tree_nodes = array();
        if ( $tree && !empty($tree_nodes[(int)$tree]) ) {
            return $tree_nodes[$tree];
        }
        if((int)$tree){
            $list = $this->_getAuthMenuTable()->fetchLimit(0,10000,array(),array(),array('sort'=>'asc'))->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['url'] = $value['url'];
            }
            $nodes = $this->list_to_tree($list,$pk='id',$pid='pid',$child='operator',$root=0);
            foreach ($nodes as $key => $value) {
                if(!empty($value['operator'])){
                    $nodes[$key]['child'] = $value['operator'];
                    unset($nodes[$key]['operator']);
                }
            }
        }else{
            $nodes = $this->_getAuthMenuTable()->fetchLimit(0,10000,array(),array(),array('sort'=>'asc'))->toArray();
            foreach ($nodes as $key => $value) {
                $nodes[$key]['url'] = $value['url'];
            }
        }
        $tree_nodes[(int)$tree] = $nodes;
        return $nodes;
    }

    final protected function returnMenu($tree = true){
        static $tree_nodes = array();
        if(array_key_exists('city',$_COOKIE) and $_COOKIE['city'] === 1){//判断主站分站
            $where['branch'] = [0,1];
        }else{
            $where['branch'] = [0,2];
        }
        $where['hide'] = 0;
        if(array_key_exists('group',$_COOKIE) and $_COOKIE['group'] == 999){//dev如果是开发者
            $where['is_dev'] = 1;
        }else{
            $where['is_dev'] = 0;
        }
        if ( $tree && !empty($tree_nodes[(int)$tree]) ) {
            return $tree_nodes[$tree];
        }
        if((int)$tree){
            $list = $this->_getAuthMenuTable()->fetchLimit(0,10000,array(),array(),array('sort'=>'asc'))->toArray();
            foreach ($list as $key => $value) {
                $list[$key]['url'] = $value['url'];
            }
            $nodes = $this->list_to_tree($list,$pk='id',$pid='pid',$child='operator',$root=0);
            foreach ($nodes as $key => $value) {
                if(!empty($value['operator'])){
                    $nodes[$key]['child'] = $value['operator'];
                    unset($nodes[$key]['operator']);
                }
            }
        }else{
            $nodes = $this->_getAuthMenuTable()->fetchLimit(0,10000,array(),array(),array('sort'=>'asc'))->toArray();
            foreach ($nodes as $key => $value) {
                $nodes[$key]['url'] = $value['url'];
            }
        }
        $tree_nodes[(int)$tree] = $nodes;
        return $nodes;
    }


}
