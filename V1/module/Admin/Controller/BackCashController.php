<?php
/**
 * 权限控制模块
 * Date: 15-12-9
 * Time: 上午10:57
 */

namespace Admin\Controller;

use Deyi\GetCacheData\CityCache;
use Deyi\JsonResponse;
use Deyi\OutPut;
use Deyi\Paginator;
use Zend\View\Model\ViewModel;

class BackCashController extends BasisController
{
    use JsonResponse;

    public function indexAction()
    {
        $page = (int)$this->getQuery('p', 1);
        $pageSum = 10;
        $out = (int)$this->getQuery('out',0);

        //资格获取方式
        $back_type = array(
            1 => '普通退款',
            2 => '支付宝充值',
            3 => '银联充值',
            4 => '圈子发言奖励',
            5 => '购买商品奖励',
            6 => '点评商品奖励',
            7 => '点评游玩地奖励',
            8 => 'app好评奖励',
            9 => '采纳攻略',
            10 => '使用验证返利',
            11 => '后台评论管理奖励',
            19 => '延期补偿',
            20 => '资深玩家奖励',
            21 => '好想你券',
            99 => '后台手动'
        );

        $back_type = array(
            1 => '购买商品返利',
            2 => '用户返利',
            3 => '点评返利'
        );

        //所有城市
        $city_select = $this->getAllCities();

        //搜索

        $begin_time = $this->getQuery('begin_time', 0);
        $end_time = $this->getQuery('end_time', 0);

        $gettype = (int)$this->getQuery('gettype', 0);

        $user = $this->getQuery('user', '');
        $coupon_name = $this->getQuery('coupon_name', '');
        $editor = $this->getQuery('editor', '');
        $p_city = $this->chooseCity();

        $where = [];

        if((int)$begin_time > 0){//待发放
            $where['play_account_log.dateline > ?'] = strtotime($begin_time);
        }
        if((int)$end_time > 0){//正在发放
            $where['play_account_log.dateline < ?'] = strtotime($end_time)+24*3600-1;
        }
        if($coupon_name !== ''){
            $where['(coupon_name like ? or ((coupon_id = ? or bid = ?) and action_type_id = 5) )'] = ['%'.$coupon_name.'%',$coupon_name,$coupon_name];
        }

        switch ((int)$gettype)
        {
            case 1://购买商品返利
                $where['action_type_id'] = [5,10];
                break;
            case 2://用户返利
                $where['action_type_id'] = [8,9,19,20,21,99];
                break;
            case 3://点评返利
                $where['action_type_id'] = [4,11,6,7];
                break;
            default:
                $where['action_type_id'] = [4,5,6,7,8,9,10,11,13,14,15,16,19,20,21,99];
        }

        $where['action_type'] = 1;

        if($user !== ''){//ID/手机号/用户名
            $where['(play_account_log.uid =? or play_user.username = ? or play_user.phone = ?)']= [(int)$user,$user,(int)$user];
        }
        if($editor !== ''){//商品名/商品ID
            $where['play_admin.id = ? or play_admin.admin_name = ?'] = [(int)$editor,$editor];
        }
        if(!empty($p_city)){//
            $where['play_account_log.city'] = $p_city;
        }
        if($out){
            $page = 1;
            $pageSum = 1000000000;
        }
        $data = $this->_getPlayAccountLogTable()->joinUserEditorList(($page-1)*$pageSum, $pageSum, array(), $where, array('dateline' => 'DESC'))->toArray();

        $count = $this->_getPlayAccountLogTable()->joinUserEditorCount($where);


        $url = '/wftadlogin/backcash';

        $city = [];

        //所有用户
        foreach($data as &$d){
            if(in_array($d['action_type_id'],[5,10])){
                $d['back_type'] = '购买商品返利';
            }elseif(in_array($d['action_type_id'],[8,9,19,20,21,99])){
                $d['back_type'] = '用户返利';
            }elseif(in_array($d['action_type_id'],[4,11,6,7])){
                $d['back_type'] = '点评返利';
            }
        }
        if ($out > 0) {
            $out = new OutPut();

            $file_name = date('Y-m-d H:i:s', time()) . '_返现列表.csv';
            // 输出Excel列名信息

            $head = array(
                '编号',
                '返利时间',
                '返利现金',
                '用户id',
                '用户名',
                '编辑账号',
                '返利类型',
                '返利原因',
                '商品名称',
                '城市'
            );

            foreach ($data as $v) {

                $content[] = array(
                    $v['id'],
                    $v['dateline'] ? date('Y-m-d H:i', $v['dateline']) : '',
                    $v['flow_money'],
                    $v['uid'],
                    $v['username'],
                    $v['admin_name'],
                    $v['back_type'],
                    $v['description'],
                    $v['coupon_name'] ?: '',
                    $city_select[$v['city']] ?: ''
                );
            }

            $out->out($file_name, $head, $content);
            exit;
        }
        $paginator = new Paginator($page, $count, $pageSum, $url);

        $vm = new viewModel(
            array(
                'data' => $data,
                'back_type' => $back_type,
                'citys' => $city_select,
                'pageData' => $paginator->getHtml(),
                'filtercity' => CityCache::getFilterCity($p_city),
            )
        );

        return $vm;
    }

    public function recycleAction(){
        $id = (int)($this->getQuery('id', 0));
        $recycle = $this->getQuery('recycle', 0);
        $status = $this->_getQualifyTable()->update(['status' => $recycle], ['id' => $id]);

        if ($status > 0) {
            return $this->_Goto('操作成功');
        }else {
            return $this->_Goto('操作失败');
        }
    }

}