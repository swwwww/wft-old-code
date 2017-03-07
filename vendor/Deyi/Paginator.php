<?php
namespace Deyi;

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

    //页面Query参数
    protected $queryUrl;

    //页面Query参数
    protected $queryParams;

    //页面数据
    protected $data;

    /*
     * 分页类构造函数
     * @param int $currPage 当前页数
     * @param int $itemCount 分页记录总数
     * @param int $itemPerPage 每页记录数
     * @param string $queryUrl 分页链接地址
     * @param string $data 页面显示数据
     * @param string $queryParams 当前分面的QueryString参数
     * return 返回 Paginator对象
     */


    /**
     * Paginator constructor.
     * @param int $currPage 当前页数
     * @param int $itemCount 分页记录总数
     * @param int $itemPerPage 每页记录数
     * @param string $queryUrl  现在不需要传
     */
    public function __construct($currPage, $itemCount, $itemPerPage = 20, $queryUrl='')
    {
        $this->itemCount = $itemCount;
        $this->itemPerPage = $itemPerPage > 0 ? $itemPerPage : 1;
        $this->pageCount = ceil($this->itemCount / $this->itemPerPage);
        $this->currPage = $currPage > $this->pageCount ? $this->pageCount : ($currPage <= 0 ? 1 : $currPage);


        $tp = strpos($_SERVER['REQUEST_URI'], '?');
        if ($tp === false) {
            $this->queryUrl = '?';
        } else {
            $strlen = strlen($_SERVER['REQUEST_URI']);
            $this->queryUrl = substr($_SERVER['REQUEST_URI'], -$strlen, $tp) . '?';
        }



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
                $html = $this->getStyleOneHtml1();  //bootstrap   上一页12...45678..下一页
                break;
            case 2:
                $html = $this->getStyleOneHtml2();  //bootstrap   上一页12...45678..下一页
                break;
            case 5:
                $html = $this->getStyleOneHtml5();  //商家后台 上一页12...45678..下一页
                break;
            default:
                $html = $this->getStyleOneHtml(); //bootstrap   上一页 123456789  下一页
                break;
        }
        return $html;
    }

    public function getPageCount()
    {
        return $this->pageCount;
    }

    public function getCurrentPage()
    {
        return $this->currPage;
    }

    public function getItemPerPage()
    {
        return $this->itemPerPage;
    }

    /**
     * 返回limit查询所需offset值
     * return int limit offset值
     */
    public function getOffset()
    {
        return $this->itemCount === 0 ? 0 : $this->getCurrentPage() * $this->itemPerPage - $this->itemPerPage;
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
                $this->queryParams['p'] = $this->currPage - 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a> </li>';
            }


            if ($this->currPage >= 6) {
                $this->queryParams['p'] = 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">1</a> </li>';
                $this->queryParams['p'] = 2;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">2</a> </li>';
                $html .= ' <li> <a href="javascript:;">...</a></li>';
                $max = $this->currPage + 2;
                $min = $this->currPage - 2;
                for ($p = $min; $p <= $max and $p <= $this->pageCount; $p++) {
                    $this->queryParams['p'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            } else {
                for ($p = 1; $p <= 7 and $p <= $this->pageCount; $p++) {
                    $this->queryParams['p'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
                }
            }
            if ($p < $this->pageCount) {
                $html .= ' <li> <a href="javascript:;">...</a></li>';
            }
            if ($this->currPage < $this->pageCount) {
                $this->queryParams['p'] = $this->currPage + 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a> </li>';
                $this->queryParams['p'] = $this->pageCount;
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
                //$this->queryParams['p'] = $this->currPage - 1;
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
            }else{
                $this->queryParams['page'] = $this->pageCount;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">&gt;</a> </li>';
            }

        }
        return $html;
    }



    private function getStyleOneHtml5()
    {
        $html = '';
        if ($this->pageCount > 0) {
            $html = '<div class="pages">';
            $html .= '<span>' . $this->itemCount . '条记录' . $this->currPage . '/' . $this->pageCount . '页</span>';

            if ($this->currPage > 1) {
                $this->queryParams['p'] = 1;

                $html .= ' <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">首页</a> ';
                $this->queryParams['p'] = $this->currPage - 1;
                $html .= '  <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a>  ';
            }


            if ($this->currPage >= 6) {
                $this->queryParams['p'] = 1;
                $html .= '   <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">1</a>  ';
                $this->queryParams['p'] = 2;
                $html .= '   <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">2</a>  ';
                $html .= '   <a href="javascript:;">...</a> ';
                $max = $this->currPage + 2;
                $min = $this->currPage - 2;
                for ($p = $min; $p <= $max and $p <= $this->pageCount; $p++) {
                    $this->queryParams['p'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <a ' . $currClass . ' href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a>  ';
                }
            } else {
                for ($p = 1; $p <= 7 and $p <= $this->pageCount; $p++) {
                    $this->queryParams['p'] = $p;
                    $currClass = $this->currPage == $p ? ' class="active"' : '';
                    $html .= ' <a ' . $currClass . ' href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a>';
                }
            }
            if ($p < $this->pageCount) {
                $html .= ' <a href="javascript:;">...</a>';
            }
            if ($this->currPage < $this->pageCount) {
                $this->queryParams['p'] = $this->currPage + 1;
                $html .= ' <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a>';
                $this->queryParams['p'] = $this->pageCount;
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
                $this->queryParams['p'] = 1;

                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">首页</a> </li>';
                $this->queryParams['p'] = $this->currPage - 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">上一页</a> </li>';
            }

            for ($p = 1; $p <= $this->pageCount; $p++) {
                $this->queryParams['p'] = $p;
                $currClass = $this->currPage == $p ? ' class="active"' : '';
                $html .= ' <li ' . $currClass . ' > <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">' . $p . '</a> </li>';
            }

            if ($this->currPage < $this->pageCount) {
                $this->queryParams['p'] = $this->currPage + 1;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">下一页</a> </li>';
                $this->queryParams['p'] = $this->pageCount;
                $html .= ' <li> <a href="' . $this->queryUrl . http_build_query($this->queryParams) . '">末页</a> </li> </ul>';
            }

        }
        return $html;
    }
}