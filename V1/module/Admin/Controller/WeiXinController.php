<?php

namespace Admin\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Sql\Expression;

use Deyi\BaseController;
use Deyi\JsonResponse;
use Deyi\Paginator;
use library\Service\System\Cache\RedCache;
use Deyi\WeiXinFun;
use Deyi\Upload;
use Deyi\ImageProcessing;

class WeiXinController extends BasisController
{
    use JsonResponse;
    //use BaseController;

    /**
     * 微信自动回复关键字列表
     * @return array
     */
    public function indexAction()
    {
        $page   = (int)$this->getQuery('p', 1);
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where = [];
        $order = ['id' => 'desc'];
        $cols  = [];
        $data  = [];
        $coIds = [];//各回复对应关键字id，下面用于查找对应的关键字

        //回复关键字搜索
        $r_key = isset($_GET['reply']) ? substr($_GET['reply'], 0, 200) : '';
        if ($r_key !== '' && $r_key !== false) {
            $where['message like ?'] = '%' . $r_key . '%';
        }

        //回复内容
        $reply_data = $this->_getWeiXinReplyContent()->fetchLimit($offset, $limit, $cols, $where, $order);

        //回复内容条数
        $count = $this->_getWeiXinReplyContent()->fetchCount($where);

        //重构数据
        foreach ($reply_data as $rd) {
            $coIds[] = $rd->id;
            $data[$rd->id] = $rd;
        }

        //查找关键字数据
        $kWhere = [];
        if (count($coIds)) {
            $kWhere['content_id'] = $coIds;
        }
        $k_key = isset($_GET['k']) ? substr($_GET['k'], 0, 200) : '';
        if ($k_key !== '' && $k_key !== false) {
            $kWhere['keyword like ?'] = '%' . $k_key . '%';
        }

        $keywords = $this->_getWeiXinReplyKeyword()->fetchAll($kWhere);
        foreach ($keywords as $kv) {
            if (isset($data[$kv->content_id])) {
                $data[$kv->content_id]['keywords'][] = $kv;
            }
        }

        //筛选关键字和回复，必须两者都不为空
        foreach ($data as $dk => $dv) {
            if (!isset($dv['keywords']) || !count($dv['keywords'])) {
                unset($data[$dk]);
            }
        }

        //创建分页
        $url = '/wftadlogin/weixin';
        $paginator = new Paginator($page, $count, $limit, $url);

        return [
            'data'     => $data,
            'pagedata' => $paginator->getHtml()
        ];
    }

    /**
     * 编辑关键字
     */
    public function addAction()
    {
        $id = $this->getQuery('id');
        $data = [];//自动回复关键字数据
        if ($id) {
            $kwds = [];//关键字数组
            $data = $this->_getWeiXinReplyContent()->get(['id' => $id]);//本id对应的回复内容
            $kw_data  = $this->_getWeiXinReplyKeyword()->fetchAll(['content_id' => $id])->toArray();//本回复对应的所有关键字
            foreach ($kw_data as $kw) {
                $kwds[] = $kw['keyword'];
            }
            $kwstr             = implode("\n", $kwds);
            $data['keyword']   = $kwstr;
            $data['match_all'] = $kw_data[0]['match_all'];
        }

        return new ViewModel([
            'data' => $data
        ]);
    }

    /**
     * 添加微信自动回复关键字
     * @return ViewModel
     */
    public function updateAction()
    {
        $id = intval($this->getQuery('id'));
        $old_keys = [];//旧的关键字
        if ($id) {
            //id存在表示是编辑
            $reply = $this->_getWeiXinReplyContent()->get(['id' => $id]);
            if (!$reply) {
                return $this->_Goto('添加失败：该回复不存在');
            }
            //修改之前的关键字
            $key_data = $this->_getWeiXinReplyKeyword()->fetchAll(['content_id' => $id]);
            foreach ($key_data as $kd) {
                $old_keys[] = $kd->keyword;
            }
        }

        if ($this->getPost('keywords')) {
            $keyrowds = explode("\n", $this->getPost('keywords'));//新的关键字
            foreach ($keyrowds as $kk => $kws) {
                if (!strlen($this->trimall($kws))) {
                    //过滤字元素全部为空字符的字符串
                    unset($keyrowds[$kk]);
                }
                $keyrowds[$kk] = $this->trimall($kws);
            }

            if (!count($keyrowds)) {
                return $this->_Goto('关键字不能为空！');
            }

            $content  = $this->getPost('content');
            $match_al = intval($this->getPost('match_all')) == 1 ? 1 : 0;
            $type     = $this->getPost('type');
            $title    = $this->getPost('title');
            $to_url   = $this->getPost('to_url');
            $description = $this->getPost('description');

            //下面图片信息保存二选一，以图片链接优先保存
            $img      = $this->getPost('img');//图片链接地址
            $thumb    = $this->getPost('thumb');//上传的图片
            if (!$img && $thumb) {
                $surface_class = new ImageProcessing($_SERVER['DOCUMENT_ROOT'] . $thumb);
                $surface_status = $surface_class->scaleResizeImage(360, 360);
                if ($surface_status) {
                    $surface_status->save($_SERVER['DOCUMENT_ROOT'] . $thumb);
                }
            }

            if ($type == 'text') {
                if (!strlen($this->trimall($content))) {
                    return $this->_Goto('回复内容不能为空！');
                }
                $data = [
                    'type'    => 'text',
                    'message' => $content,
                    'img'     => '',
                    'to_url'  => '',
                    'title'   => '',
                    'description' => ''
                ];
            } elseif ($type == 'news') {
                $data = [
                    'type'    => 'news',
                    'message' => '',//图文回复时没有回复内容
                    'img'     => $img ? $img : $thumb,
                    'to_url'  => $to_url,
                    'title'   => $title,
                    'description' => $description
                ];
                if (!$data['img'] or !$data['title'] or !$data['to_url']) {
                    return $this->_Goto('数据不能为空！');
                }
            } else {
                $data = [];
            }

            if (count($data)) {
                if ($id) {
                    //修改回复内容，返回修改的行数
                    $this->_getWeiXinReplyContent()->update($data, ['id' => $id]);
                    $old_keys_str = implode(',', $old_keys);
                    $new_keys_str = implode(',', $keyrowds);
                    if ($old_keys_str !== $new_keys_str) {
                        //如果修改关键字则执行删除和新增操作
                        //删除旧的关键字
                        $this->_getWeiXinReplyKeyword()->delete(['content_id' => $id]);
                        //插入新的关键字
                        $add_keys = [];//新增时的关键字数据
                        foreach ($keyrowds as $v) {
                            $add_keys[] = [
                                'keyword'    => '\'' . $v . '\'',//字符串类型的数据写入数据库前要加单引号
                                'content_id' => $id,
                                'match_all'  => $match_al
                            ];
                        }
                        $this->batchInsert($add_keys);//批量写入
                    } else {
                        //如果没有修改关键字，只修改匹配类型则执行修改操作
                        $this->_getWeiXinReplyKeyword()->update(['match_all' => $match_al], ['content_id' => $id]);
                    }
                    return $this->_Goto('修改成功', '/wftadlogin/weixin');
                } else {
                    //新增
                    $status = $this->_getWeiXinReplyContent()->insert($data);//创建回复内容成功的标志
                    if ($status) {
                        $lastid   = $this->_getWeiXinReplyContent()->getlastInsertValue();//对应的回复id
                        $add_keys = [];//新增时的关键字
                        foreach ($keyrowds as $v) {
                            $add_keys[] = [
                                'keyword'    => '\'' . $v . '\'',//字符串类型的数据写入数据库前要加单引号
                                'content_id' => $lastid,
                                'match_all'  => $match_al
                            ];
                        }
                        $this->batchInsert($add_keys);//批量写入
                        return $this->_Goto('添加成功', '/wftadlogin/weixin');
                    } else {
                        return $this->_Goto('添加失败');
                    }
                }
            } else {
                return $this->_Goto('添加失败：没有数据');
            }
        } else {
            return $this->_Goto('操作失败：没有数据');
        }
    }

    /**
     * 删除微信自动回复关键字
     * @return ViewModel
     */
    public function deleteAction()
    {
        $id = $this->getQuery('id');
        if ($id) {
            $status = $this->_getWeiXinReplyContent()->delete(['id' => $id]);
            $this->_getWeiXinReplyKeyword()->delete(['content_id' => $id]);
            if ($status) {
                return $this->_Goto('删除成功');
            } else {
                return $this->_Goto('删除失败');
            }
        } else {
            return $this->_Goto('删除失败：参数错误');
        }
    }

    /**
     * 设置微信自定义菜单
     * @return array|ViewModel
     */
    public function setMenuAction()
    {
        $pmid = $_GET['pmid'];
        $where = [];
        if ($pmid) {
            $where['button'] = 2;
            $where['pmid']   = intval($pmid);
        } else {
            $where['button'] = 1;
            $where['pmid']   = 0;
        }
        $menus = $this->_getWeixinMenuTable()->fetchAll($where);
        return new ViewModel([
            'menus' => $menus
        ]);
    }

    /**
     * 添加菜单
     */
    public function editMenuAction()
    {
        $id = $this->getQuery('id');
        $menu = [];
        $second_menu_num = 0;
        if ($id) {
            $menu = $this->_getWeixinMenuTable()->get(['id' => $id]);
            $second_menu_num = $this->_getWeixinMenuTable()->fetchCount(['button' => 2, 'pmid' => $id]);
        }

        $first_menu = $this->_getWeixinMenuTable()->fetchAll(['pmid' => 0])->toArray();
        return new ViewModel([
            'first_menu' => $first_menu,
            'menu' => $menu,
            'second_menu_num' => $second_menu_num
        ]);
    }

    /**
     * 编辑菜单
     */
    public function updateMenuAction()
    {
        $id = $this->getQuery('id');
        $data = [];
        $menu = [];
        if ($id) {
            $menu = $this->_getWeixinMenuTable()->get(['id' => $id]);
            if (!$menu) {
                return $this->_Goto('操作失败：该菜单不存在');
            }
        }

        //TODO 接收页面传过来的菜单参数
        $pmid       = $this->getPost('pmid');//父级菜单id
        $type       = $this->getPost('type');//菜单类型
        $menu_level = $this->getPost('button');//菜单等级：一级或二级
        $menu_name  = substr($this->getPost('menu_name'), 0, 40);//菜单标题
        $url        = $this->getPost('url');//菜单链接

        if (!in_array($menu_level, ['1', '2'])) {
            return $this->_Goto('操作失败：没有选择菜单级别');
        }
        if (!$pmid && $menu_level == '2') {
            return $this->_Goto('操作失败：当前没有一级菜单，无法创建二级菜单');
        }
        $data['button'] = intval($menu_level);
        $data['pmid']   = intval($pmid);

        if (!in_array($type, ['view', 'click'])) {
            return $this->_Goto('操作失败：菜单类型错误');
        }
        $data['type'] = $type;

        if (!$menu_name) {
            return $this->_Goto('操作失败：没有填写菜单标题');
        }
        $data['menu_name'] = $menu_name;

        if (!$url) {
            //return $this->_Goto('操作失败：没有填写菜单链接');
        }
        $data['url'] = $url;

        //TODO 将菜单数据更新到数据库
        if (!$id) {
            //新增菜单
            if ($menu_level == '2') {
                //新增二级菜单
                $second_menu_num = $this->_getWeixinMenuTable()->fetchCount(['button' => 2, 'pmid' => $menu->pmid]);
                if ($second_menu_num >= 5) {
                    return $this->_Goto('添加失败：本菜单下的二级菜单已经有5个啦');
                }
            } else {
                //新增一级菜单
                $first_menu_num = $this->_getWeixinMenuTable()->fetchCount(['button' => 1, 'pmid' => 0]);
                if ($first_menu_num >= 3) {
                    return $this->_Goto('添加失败：已经有3个一级菜单了，请改为添加二级菜单');
                }
            }
            $this->_getWeixinMenuTable()->insert($data);
            return $this->_Goto('添加成功');
        } else {
            //修改菜单 父级菜单id、菜单级别和菜单类型不允许修改，只能修改菜单标题和链接
            unset($data['pmid']);
            unset($data['button']);
            unset($data['type']);
            $this->_getWeixinMenuTable()->update($data, ['id' => $id]);
            return $this->_Goto('修改成功');
        }
    }

    /**
     * 删除菜单
     */
    public function deleteMenuAction()
    {
        $id = $this->getQuery('id');
        if ($id) {
            $second_menu_num = $this->_getWeixinMenuTable()->fetchCount(['button' => 2,'pmid' => $id]);
            if ($second_menu_num) {
                return $this->_Goto('删除失败：请先删除子菜单');
            }
            $this->_getWeixinMenuTable()->delete(['id' => $id]);
            return $this->_Goto('删除成功');
        } else {
            return $this->_Goto('删除失败：菜单不存在');
        }
    }

    /**
     * 微信用户关注情况
     */
    public function concernAction()
    {
        $curr_page = (int)$this->getQuery('p', 1);//当前页码，默认为第1页
        $limit     = 30;//每页显示20条记录
        $offset    = ($curr_page - 1) * $limit;//每页起始数据索引

        $data  = [];//数据
        $where = [];//搜索条件

        //用户当前是否关注
        $is_on = isset($_GET['is_on']) ? $_GET['is_on'] : '';
        if (in_array($is_on, ['0', '1'])) {
            $where['is_on'] = intval($is_on);
        }

        //渠道关键字搜索
        $scene = isset($_GET['scene']) ? substr($_GET['scene'], 0, 200) : '';
        if ($scene !== '' && $scene !== false) {
            $where['scene'] = $scene;
        }

        //微信公众号关键字搜索
        $weixin_name = isset($_GET['weixin_name']) ? substr($_GET['weixin_name'], 0, 200) : '';
        if ($weixin_name !== '' && $weixin_name !== false) {
            $where['weixin_name like ?'] = '%' . $weixin_name . '%';
        }

        if (isset($_GET['act']) && $_GET['act'] == 'all') {
            //TODO 查看所有关注记录
            //要查询的字段
            $cols = [
                'id', 'open_id', 'scene', 'weixin_name', 'concern_num',
                'union_id', 'nick_name', 'is_on', 'concern_time'
            ];
            $order = ['id' => 'desc'];

            //关注时间段开始
            $begin_time = isset($_GET['begin_time']) && $_GET['begin_time'] ? $_GET['begin_time'] : 0;
            //关注时间段结束
            $end_time   = isset($_GET['end_time']) && $_GET['end_time'] ? $_GET['end_time'] : 0;
            if ($begin_time && $end_time) {
                $where['concern_time >= ?'] = strtotime($begin_time . '00:00');
                $where['concern_time <= ?'] = strtotime($end_time . '23:59');
            } elseif ($begin_time && !$end_time) {
                $where['concern_time >= ?'] = strtotime($begin_time . '00:00');
                $where['concern_time <= ?'] = strtotime($begin_time . '23:59');
            } elseif (!$begin_time && $end_time) {
                $where['concern_time >= ?'] = strtotime($end_time . '00:00');
                $where['concern_time <= ?'] = strtotime($end_time . '23:59');
            }

            //用户昵称关键字搜索
            $nick_name  = isset($_GET['nick_name']) ? substr($_GET['nick_name'], 0, 200) : '';
            if ($nick_name !== '' && $nick_name !== false) {
                $where['nick_name like ?'] = '%' . $nick_name . '%';
            }

            //用户关注次数
            $concern_num = isset($_GET['concern_num']) ? $_GET['concern_num'] : '';
            if ($concern_num !== '') {
                $where['concern_num'] = intval($concern_num);
            }

            $a_res = $this->_getWeixinDituiLogTable()->fetchLimit($offset, $limit, $cols, $where, $order);
            foreach ($a_res as $r) {
                $data[] = $r;
            }

            //数据条数
            $count = $this->_getWeixinDituiLogTable()->fetchCount($where);
        } else {
            //TODO 查看渠道关注数量
            //要查询的字段
            $cols  = ['scene', 'weixin_name', 'concern_time', 'num' => new Expression('count(*)')];
            $group = ['scene', 'weixin_name'];

            $s_res = $this->_getWeixinDituiLogTable()->getSceneConcernData($offset, $limit, $cols, $where, $group);
            foreach ($s_res as $sr) {
                $data[] = $sr;
            }

            //数据条数
            $count = $this->_getWeixinDituiLogTable()->getSceneConcernData(0, 0, $cols, $where, $group)->count();
        }

        //创建分页
        $baseUrl   = '/wftadlogin/weixin/concern';
        $paginator = new Paginator($curr_page, $count, $limit, $baseUrl);

        return [
            'data'     => $data,
            'pagedata' => $paginator->getHtml()
        ];
    }

    /**
     * 微信用户关注情况数据导出
     */
    public function concernExportAction()
    {
        $data  = [];//数据

        //用户当前是否关注
        $where = [];//搜索条件
        $is_on = isset($_GET['is_on']) ? $_GET['is_on'] : '';
        if (in_array($is_on, ['0', '1'])) {
            $where['is_on'] = intval($is_on);
        }

        //渠道关键字搜索
        $scene = isset($_GET['scene']) ? substr($_GET['scene'], 0, 200) : '';
        if ($scene !== '') {
            $where['scene'] = $scene;
        }

        //微信公众号关键字搜索
        $weixin_name = isset($_GET['weixin_name']) ? substr($_GET['weixin_name'], 0, 200) : '';
        if ($weixin_name !== '') {
            $where['weixin_name like ?'] = '%' . $weixin_name . '%';
        }


        //生成excel
        include_once($_SERVER['DOCUMENT_ROOT'] . '/../vendor/Deyi/PHPExcel/PHPExcel.php');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator('system')
            ->setLastModifiedBy('Diana')
            ->setTitle('Document')
            ->setSubject('Document')
            ->setDescription('')
            ->setKeywords('Weixin')
            ->setCategory('wft');


        //处理数据
        if (isset($_GET['act']) && $_GET['act'] == 'all') {
            //TODO 查看所有关注记录
            //要查询的字段
            $cols = [
                'id', 'open_id', 'scene', 'weixin_name', 'concern_num',
                'union_id', 'nick_name', 'is_on', 'concern_time'
            ];
            $order = ['id' => 'desc'];

            //TODO 各关键字搜索

            //关注时间段开始
            $begin_time = isset($_GET['begin_time']) && $_GET['begin_time'] ? $_GET['begin_time'] : 0;
            //关注时间段结束
            $end_time   = isset($_GET['end_time']) && $_GET['end_time'] ? $_GET['end_time'] : 0;
            if ($begin_time && $end_time) {
                $where['concern_time >= ?'] = strtotime($begin_time . '00:00');
                $where['concern_time <= ?'] = strtotime($end_time . '23:59');
            } elseif ($begin_time && !$end_time) {
                $where['concern_time >= ?'] = strtotime($begin_time . '00:00');
                $where['concern_time <= ?'] = strtotime($begin_time . '23:59');
            } elseif (!$begin_time && $end_time) {
                $where['concern_time >= ?'] = strtotime($end_time . '00:00');
                $where['concern_time <= ?'] = strtotime($end_time . '23:59');
            }

            //用户昵称关键字搜索
            $nick_name  = isset($_GET['nick_name']) ? substr($_GET['nick_name'], 0, 200) : '';
            if ($nick_name !== '' && $nick_name !== false) {
                $where['nick_name like ?'] = '%' . $nick_name . '%';
            }

            //用户关注次数
            $concern_num = isset($_GET['concern_num']) ? $_GET['concern_num'] : '';
            if ($concern_num !== '') {
                $where['concern_num'] = intval($concern_num);
            }

            $res = $this->_getWeixinDituiLogTable()->fetchLimit(0, 0, $cols, $where, $order);
            foreach ($res as $r) {
                $data[] = $r;
            }

            if (!count($data)) {
                return $this->_Goto('共0条数据');
            }

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', '数据ID')
                ->setCellValue('B1', '用户加密的微信号')
                ->setCellValue('C1', '用户昵称')
                ->setCellValue('D1', '当前是否关注')
                ->setCellValue('E1', '关注次数')
                ->setCellValue('F1', '最后关注时间')
                ->setCellValue('G1', '渠道')
                ->setCellValue('H1', '微信公众号');

            $k = 1;
            foreach ($data as $v) {
                $k++;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A' . $k, $v->id)
                    ->setCellValue('B' . $k, $v->open_id)
                    ->setCellValue('C' . $k, $v->nick_name)
                    ->setCellValue('D' . $k, $v->is_on == 1 ? '关注' : '未关注')
                    ->setCellValue('E' . $k, $v->concern_num)
                    ->setCellValue('F' . $k, date('Y-m-d H:i', $v->concern_time))
                    ->setCellValue('G' . $k, $v->scene)
                    ->setCellValue('H' . $k, $v->weixin_name);
            }

            //默认页
            $objPHPExcel->setActiveSheetIndex(0);
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

            $filename = '微信渠道数据';
            if ($weixin_name !== '' && $weixin_name !== false) {
                $filename .= '_' . $weixin_name;
            }

            if ($scene !== ''  && $scene !== false) {
                $filename .= '_' . $scene;
            }

            if ($begin_time && $end_time) {
                $filename .= date('Y.m.d', $begin_time) . '-' . date('Y.m.d', $end_time);
            } elseif ($begin_time && !$end_time) {
                $filename .= date('Y.m.d', $begin_time);
            } elseif (!$begin_time && $end_time) {
                $filename .= date('Y.m.d', $end_time);
            }
        } else {
            //TODO 查看渠道关注数量
            //要查询的字段
            $cols  = ['scene', 'weixin_name', 'concern_time', 'num' => new Expression('count(*)')];
            $group = ['scene', 'weixin_name'];

            $res = $this->_getWeixinDituiLogTable()->getSceneConcernData(0, 0, $cols, $where, $group);
            foreach ($res as $r) {
                $data[] = $r;
            }

            if (!count($data)) {
                return $this->_Goto('共0条数据');
            }

            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue('A1', 'ID')
                ->setCellValue('B1', '微信公众号')
                ->setCellValue('C1', '渠道')
                ->setCellValue('D1', '关注人数');

            $k = 1;
            foreach ($data as $v) {
                $k++;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValue('A' . $k, $k - 1)
                    ->setCellValue('B' . $k, $v->weixin_name)
                    ->setCellValue('C' . $k, $v->scene)
                    ->setCellValue('D' . $k, $v->num);
            }

            //默认页
            $objPHPExcel->setActiveSheetIndex(0);
            $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');

            $filename = '微信渠道数据';
            if ($weixin_name !== '' && $weixin_name !== false) {
                $filename .= '_' . $weixin_name;
            }

            if ($scene !== '' && $scene !== false) {
                $filename .= '_' . $scene;
            }
            $filename .= '_统计';
        }

        $this->exportSet($filename);
        $objWriter->save('php://output');
        exit;
    }

    /**
     * 设置导出的Excel文件属性
     * @param string $filename 文件名
     */
    private function exportSet($filename)
    {
        $filename .= '.xls';
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $filename);
        header("Content-Transfer-Encoding:binary");
    }

    /**
     * 获得微信操作对象
     * @return WeiXinFun
     */
    public function getWeiXin()
    {
        return new WeiXinFun($this->_getConfig()['wanfantian_weixin']);
    }

    /**
     * 批量写入关键字信息
     * @param $data
     */
    private function batchInsert($data)
    {
        if (is_array($data) && count($data)) {
            $tableName = 'weixin_reply_keyword';
            $columns   = [];
            $vals      = [];
            foreach ($data as $k => $v) {
                if ($k == 0) {
                    $columns = '('. implode(',', array_keys($v)). ')';
                }
                $vals[] = '('. implode(',', array_values($v)). ')';
            }
            $values = implode(',', $vals);
            $sql = sprintf("INSERT INTO %s %s VALUES %s", $tableName, $columns, $values);
            $this->_getAdapter()->query($sql)->execute();
        }
    }

    /**
     * 删除字符串中的空格
     * @param $str
     *
     * @return mixed
     */
    public function trimall($str)
    {
        $qian = [" ","　","\t","\n","\r"];
        $hou  = ["","","","",""];
        return str_replace($qian, $hou, $str);
    }

    /**
     * 根据现在本地菜单生成线上微信菜单
     * @return array|ViewModel
     */
    public function generateMenuAction()
    {
        //TODO 生成新的菜单  方法是从数据库中获取当前最新的菜单数据拼接成json并调用微信算定义菜单生成接口
        $menu_data   = [];
        $first_menu  = $this->_getWeixinMenuTable()->fetchAll(['button' => '1', 'pmid' => 0])->toArray();//所有一级菜单
        $second_menu = [];//所有二级菜单
        foreach ($first_menu as $fk => $fm) {
            //当前父菜单下的所有子菜单
            $second_menu[$fm['id']] = $this->_getWeixinMenuTable()->fetchAll(['button' => '2', 'pmid' => $fm['id']])->toArray();
            $smc = count($second_menu[$fm['id']]);//当前菜单下二级菜单个数
            if ($smc >= 1) {
                $menu_data['button'][$fk]['name'] = $fm['menu_name'];
                foreach ($second_menu[$fm['id']] as $smf) {
                    $menu_data['button'][$fk]['sub_button'][] = [
                        'type' => $smf['type'],
                        'name' => $smf['menu_name'],
                        'url'  => $smf['url']
                    ];
                }
            } else {
                $menu_data['button'][$fk] = [
                    'type' => $fm['type'],
                    'name' => $fm['menu_name'],
                    'url'  => $fm['url']
                ];
            }
        }

        $weixin   = $this->getWeiXin();
        $menuJson = json_encode($menu_data, JSON_UNESCAPED_UNICODE);//json编码，不转成unicode编码
        $menuJson = str_replace('\/', '/', $menuJson);//将被转义的反斜杠符号修正回来
        if ($menu_data && $menuJson) {
            $resp = $weixin->setMenu($menuJson);
            if ($resp['errcode'] === 0) {
                return $this->_Goto('操作成功' . $resp['errcode']);
            } else {
                return $this->_Goto('操作失败 error_message:' . $resp['errmsg']);
            }
        }
        return $this->_Goto('操作失败：菜单为空');
    }
}
