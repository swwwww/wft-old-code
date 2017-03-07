<?php
namespace Deyi;
class ImageProcessing
{

    public $src;     //原图像地址
    public $src_string; //原始图像流
    public $error = '';

    public $wat_src = '/work/web/bxbw.deyi.com/public/uploads/201412/03/1.png'; //水印图片路径
    public $alpha = 50;    //水印透明度  0~100

    private $src_info; //原始图片信息
    private $w = 0;   //新图形高度
    private $h = 0;  //新图像宽度
    private $max = 0;  //需要缩放的大小
    private $out_x = 0;   //新图像x偏移
    private $out_y = 0;  //新图像y偏移
    private $in_x = 0;  //原图像x偏移
    private $in_y = 0;  //原图像y偏移
    private $cut_w = 0;  //需要copy源图像的宽(加上偏移)
    private $cut_h = 0;  //需要copy源图像的高(加上偏移)
    private $image;     //处理后的图像对象


    function __construct($in_src = '', $in_string = '')
    {
        if (file_exists($in_src)) {
            $this->src = $in_src;
            $this->src_string = imagecreatefromstring(file_get_contents($in_src));
            $this->src_info = getimagesize($in_src);
        } else {
            // 等待测试
            $this->src_string = $in_string;
            $this->src_info = imagecreatefromstring($in_string);   //or getimagesizefromstring()
        }

        $this->w = $this->src_info[0];
        $this->h = $this->src_info[1];
        $this->cut_w = $this->src_info[0];
        $this->cut_h = $this->src_info[1];

        if (!$this->src_info) {
            $this->error = '图像不存在';
            return false;
        }
    }


    /**按最大边 等比缩放
     * @param $max
     * @return $this
     */
    public function MaxResizeImage($max)
    {
        $this->max = $max;
        if ($this->w > $this->h) {
            $this->w = $this->max;
            $this->h = $this->h * ($this->max / $this->src_info['0']);
        } else {
            $this->h = $this->max;
            $this->w = $this->w * ($this->max / $this->src_info['1']);
        }
        $this->change();
        return $this;
    }

    /**按比例截取 $w 宽 $h 高
     * @param $w
     * @param $h
     * @return $this|bool
     */
    public function scaleResizeImage($w, $h)
    {
        $this->src_info['0'];
        $this->src_info['1'];
        if ($this->src_info['0'] <= $w && $this->src_info['1'] <= $h) {
            if ($this->src_info['0'] / $this->src_info['1'] == $w / $h) {
                return false;
            }
            if (($this->src_info['0'] / $w) > ($this->src_info['1'] / $h)) {
                $this->w = $this->src_info['1'] * ($w / $h);
                $this->h = $this->src_info['1'];
            } else {
                $this->w = $this->src_info['0'];
                $this->h = $this->src_info['0'] * ($h / $w);
            }
        } else {
            if (($this->src_info['0'] / $w) > ($this->src_info['1'] / $h)) {
                $this->h = ($this->src_info['1'] > $h) ? $h : $this->src_info['1'];
                $this->w = $this->h * ($w / $h);
            } else {
                $this->w = ($this->src_info['0'] > $w) ? $w : $this->src_info['0'];
                $this->h = $this->w * ($h / $w);
            }
        }
        $this->in_x = ($this->src_info['0'] - $this->w) / 2;
        $this->in_y = ($this->src_info['1'] - $this->h) / 2;
        $this->cut_w = $this->w;
        $this->cut_h = $this->h;

        $this->change();
        return $this;
    }


    /**按宽 等比缩放
     * @param $max
     * @return $this
     */
    public function MaxWidthResizeImage($max)
    {
        $this->max = $max;
        $this->out_x = $this->out_y = $this->in_x = $this->in_y = 0;
        $this->w = $this->max;
        $this->h = $this->h * ($this->max / $this->src_info['0']);
        $this->change();
        return $this;
    }


    /** 截取正方形 最大边等边截取
     * @return $this
     */
    public function MaxSquareResizeImage()
    {
        if ($this->w > $this->h) {
            $this->in_x = ($this->src_info[0] - $this->h) / 2;
            $this->in_y = 0;
            $this->cut_w = $this->cut_h = $this->h;
        } else {
            $this->max = $this->h = $this->w;
            $this->in_x = 0;
            $this->in_y = ($this->src_info[1] - $this->w) / 2;
            $this->cut_w = $this->cut_h = $this->w;
        }
        $this->change();
        return $this;
    }

    /**
     * 截取正方形 最大边等边截取缩放
     */
    public function MaxSquareZoomResizeImage($max)
    {
        $this->max = $max;
        if ($this->w > $this->h) {
            $this->in_x = ($this->src_info[0] - $this->h) / 2;
            $this->in_y = 0;
            $this->cut_w = $this->cut_h = $this->h;
            $this->w = $this->h = $this->max;
        } else {
            $this->in_x = 0;
            $this->in_y = ($this->src_info[1] - $this->w) / 2;
            $this->cut_w = $this->cut_h = $this->w;
            $this->w = $this->h = $this->max;
        }
        $this->change();
        return $this;
    }

    /** 生成水印
     * @param int $wat_x
     * @param int $wat_y
     * @return bool
     */
    public function CopyImage($wat_x = 0, $wat_y = 0)
    {
        $src = $this->wat_src; //水印图片
        $src_im = imagecreatefrompng($src);
        $src_info = getimagesize($src);
        if ($this->src_info[0] > ($src_info[0] + 20) and $this->src_info[1] > ($src_info[1] + 20)) {
            $this->imagecopymerge_alpha($this->src_string, $src_im, $this->src_info[0] - $src_info[0] - 20, $this->src_info[1] - $src_info[1] - 20, 0, 0, $src_info[0], $src_info[1], $this->alpha);
        }
        return $this;
    }

    /**
     * 重新保存图片
     * @param $dir_name |存储目录
     * @param $quality |图像清晰度
     * @return bool
     */
    public function Save($dir_name, $quality = 100)
    {
        //保存到指定目录
        $s = imagejpeg($this->image, $dir_name, $quality);
        imagedestroy($this->image);//销毁资源
        unset($this->src_string);//销毁资源
        return $s;
    }

    private function change()
    {
        $this->image = imagecreatetruecolor($this->w, $this->h);

         $color=imagecolorallocate($this->image,255,255,255);
        //设置透明
         imagecolortransparent($this->image,$color);
          imagefill($this->image,0,0,$color);

        //关键函数，参数（目标资源，源，目标资源的开始坐标x,y, 源资源的开始坐标x,y,目标资源的宽高(加上偏移)w,h,需要截取的源资源的宽高(加上偏移)w,h）
        imagecopyresampled($this->image, $this->src_string, $this->out_x, $this->out_y, $this->in_x, $this->in_y, $this->w, $this->h, $this->cut_w, $this->cut_h);

        /*//告诉浏览器以图片形式解析
        header('content-type:image/png');
        imagepng($this->image);
        //销毁资源
        imagedestroy($this->image);
        exit;*/
    }

    //添加水印
    private function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        $opacity = $pct;
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        $opacity = 100 - $opacity;
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        $s = imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
        $this->image = $dst_im;

        //告诉浏览器以图片形式解析
        /* header('content-type:image/png');
         imagepng($this->image);
         //销毁资源
         imagedestroy($this->image);
         exit;*/

        return $s;
    }

}