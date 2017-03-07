<?php
namespace library\Service\System\Paginator;

//使用示例

//$page = $this->getQuery('page', 1);
//$row = 10;
//$offset = $row * ($page - 1);
//$res = M::getTestTable()->fetchLimit(array(), array(), $offset, $row);
//return array('res' => $res->toArray(), 'page' => Paginator::getPageHtml($page, M::getTestTable()->fetchCount(), $row));
use library\Fun\Common;

/**
 * 分页程序
 * Class Paginator
 * @package library\Service\Paginator
 */
class Paginator
{

    //记录总数
    protected $itemCount;

    //页面总数
    protected $pageCount;

    //当前页面
    protected $currPage = 1;

    //每页显示页数
    protected $itemPerPage = 20;

    //页面URL
    protected $queryUrl;

    //页面Query参数
    protected $queryParams;


    /**
     * Paginator constructor.
     * @param $page
     * @param $count
     * @param int $row
     */
    public function __construct($page, $count, $row = 20)
    {
        $this->itemCount = $count;
        $this->itemPerPage = $row > 0 ? $row : 1;
        $this->pageCount = ceil($this->itemCount / $this->itemPerPage);
        $this->currPage = $page > $this->pageCount ? $this->pageCount : ($page <= 0 ? 1 : $page);
        $this->queryUrl = Common::getUrlPath() . '?';
        $this->queryParams = $_GET;
    }


    /*
     * 获取$style分格的分页HTML
     * @param int $style  分页风格id
     * return string 分页HTML
     */
    public function getHtml($style = 0)
    {

        switch ($style) {
            case 1:
                $html = $this->getStyleOneHtml1();  //bootstrap 全部   200条记录11/67页 首页 上一页 1 2 3 4 5  67 下一页 末页
                break;
            case 2:
                $html = $this->getStyleOneHtml2();  //bootstrap   < 1 2 ... 9 10 11 12 13 ... >
                break;
            case 5:
                $html = $this->getStyleOneHtml3();  //不带样式 上一页12...45678..下一页
                break;
            default:
                $html = $this->getStyleOneHtml(); //bootstrap   200条记录11/67页 上一页 1 2 ... 9 10 11 12 13 ... 下一页 末页
                break;
        }
        return $html;
    }


    /**
     * 样式： 上一页 1 2 ... 4 5 6 7 8 ... 下一页 末页
     * return string 分页样式一 HTML代码
     */
    private function getStyleOneHtml()
    {
        $html = '';
        if ($this->pageCount > 0) {
            $html = '<ul class="pagination">';
            $html .= '<li><span>' . $this->itemCount . '条记录' . $this->currPage . '/' . $this->pageCount . '页</span></li> ';

            if ($this->currPage > 1) {
                $this->queryParams['page'] = $this->currPage - 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a> </li>';
            }


            if ($this->currPage >= 6) {
                $this->queryParams['page'] = 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">1</a> </li>';
                $this->queryParams['page'] = 2;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">2</a> </li>';
                $html .= ' <li> <a href="javascript:;">...</a></li>';
                $max = $this->currPage + 2;
                $min = $this->currPage - 2;
                for ($p = $min; $p <= $max and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            } else {
                for ($p = 1; $p <= 7 and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            }
            if ($p < $this->pageCount) {
                $html .= ' <li> <a href="javascript:;">...</a></li>';
            }
            if ($this->currPage < $this->pageCount) {
                $this->queryParams['page'] = $this->currPage + 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a> </li>';
                $this->queryParams['page'] = $this->pageCount;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">末页</a> </li> </ul>';
            }

        }
        return $html;
    }


    /**
     *
     * 活动临时使用
     * 样式： 上一页 1 2 ... 4 5 6 7 8 ... 下一页 末页
     * return string 分页样式一 HTML代码
     */
    private function getStyleOneHtml2()
    {
        $html = '';
        if ($this->pageCount > 0) {
            $html = '<ul class="pagination" style="font-size: 20px;">';
            $html .= '<li><span>&lt;</span></li> ';


            if ($this->currPage > 1) {
                //$this->queryParams['page'] = $this->currPage - 1;
                // $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a> </li>';
            }


            if ($this->currPage >= 6) {
                $this->queryParams['page'] = 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">1</a> </li>';
                $this->queryParams['page'] = 2;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">2</a> </li>';
                $html .= ' <li> <a href="javascript:;">...</a></li>';
                $max = $this->currPage + 2;
                $min = $this->currPage - 2;
                for ($p = $min; $p <= $max and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            } else {
                for ($p = 1; $p <= 7 and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            }
            if ($p < $this->pageCount) {
                $html .= ' <li> <a href="javascript:;">...</a></li>';
            }
            if ($this->currPage < $this->pageCount) {
                $this->queryParams['page'] = $this->currPage + 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">&gt;</a> </li>';
//                $this->queryParams['page'] = $this->pageCount;
//                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">末页</a> </li> </ul>';
            } else {
                $this->queryParams['page'] = $this->pageCount;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">&gt;</a> </li>';
            }

        }
        return $html;
    }


    private function getStyleOneHtml3()
    {
        $html = '';
        if ($this->pageCount > 0) {
            $html = '<div class="pages">';
            $html .= '<span>' . $this->itemCount . '条记录' . $this->currPage . '/' . $this->pageCount . '页</span>';

            if ($this->currPage > 1) {
                $this->queryParams['page'] = 1;

                $html .= ' <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">首页</a> ';
                $this->queryParams['page'] = $this->currPage - 1;
                $html .= '  <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a>  ';
            }


            if ($this->currPage >= 6) {
                $this->queryParams['page'] = 1;
                $html .= '   <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">1</a>  ';
                $this->queryParams['page'] = 2;
                $html .= '   <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">2</a>  ';
                $html .= '   <a href="javascript:;">...</a> ';
                $max = $this->currPage + 2;
                $min = $this->currPage - 2;
                for ($p = $min; $p <= $max and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <a ' . $currClass . ' href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a>  ';
                }
            } else {
                for ($p = 1; $p <= 7 and $p <= $this->pageCount; $p++) {
                    $this->queryParams['page'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <a ' . $currClass . ' href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a>';
                }
            }
            if ($p < $this->pageCount) {
                $html .= ' <a href="javascript:;">...</a>';
            }
            if ($this->currPage < $this->pageCount) {
                $this->queryParams['page'] = $this->currPage + 1;
                $html .= ' <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a>';
                $this->queryParams['page'] = $this->pageCount;
                $html .= '<a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">末页</a></div>';
            }

        }
        return $html;
    }

    /*
     * 样式： 3条记录 1/3 首页 1 2 3 下一页 末页
     * return string 分页样式一 HTML代码
     */
    private function getStyleOneHtml1()
    {

        $html = '';
        if ($this->pageCount > 0) {
            $html = '<ul class="pagination">';
            $html .= '<li><span>' . $this->itemCount . '条记录' . $this->currPage . '/' . $this->pageCount . '页</span></li> ';
            if ($this->currPage > 1) {
                $this->queryParams['page'] = 1;

                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">首页</a> </li>';
                $this->queryParams['page'] = $this->currPage - 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a> </li>';
            }

            for ($p = 1; $p <= $this->pageCount; $p++) {
                $this->queryParams['page'] = $p;
                $currClass = $this->currPage == $p ? ' class="active"' : '';
                $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
            }

            if ($this->currPage < $this->pageCount) {
                $this->queryParams['page'] = $this->currPage + 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a> </li>';
                $this->queryParams['page'] = $this->pageCount;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">末页</a> </li> </ul>';
            }

        }
        return $html;
    }

    //快速静态调用方法
    public static function getPageHtml($page, $count, $row = 20, $style = 0)
    {
        $page = new Paginator($page, $count, $row);
        return $page->getHtml($style);
    }
}