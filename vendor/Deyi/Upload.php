<?php
namespace Deyi;

class Upload
{
    protected $file;
    protected $basePath;
    protected $path;
    protected $name;
    protected $fileName;
    protected $fileExtName;
    protected $fileMime;
    protected $fileType;
    protected $fileTempName;
    protected $fileSize = 0;
    protected $imgFileWidth = 0;
    protected $imgFileHeight = 0;

    public function __construct($file)
    {
        $this->basePath = __DIR__ . '/../../public'; //$_SERVER['DOCUMENT_ROOT'];
        $this->file = $file;
        $this->fileSize = intval($this->file['size']);
        $this->fileTempName = $this->file['tmp_name'];
        $this->name = $this->file['name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $this->fileMime = finfo_file($finfo, $this->fileTempName);
        finfo_close($finfo);
    }

    /*
     * @param  Array $imageType 指定检测特定图像类型
     * return  Bool 返回布尔型
     */
    public function isImage()
    {
        $imgMime = array(
            'image/gif',
            'image/jpg',
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/x-png',
        );

        $isImage = FALSE;
        if (in_array($this->fileMime, $imgMime)) {
            $imageInfo = getimagesize($this->fileTempName);
            $this->imgFileWidth = $imageInfo[0];
            $this->imgFileHeight = $imageInfo[1];
            $isImage = TRUE;
        }
        return $isImage;
    }

    /*
     * return Int 返回文件的大小，单位为KB
     */
    public function getSize()
    {
        return round($this->fileSize / 1024);
    }

    public function getWidth()
    {
        return $this->imgFileWidth;
    }

    public function getHeight()
    {
        return $this->imgFileHeight;
    }

    public function getFileExtName()
    {
        switch ($this->fileMime) {
            case 'image/jpg':
            case 'image/jpeg':
            case 'image/pjpeg':
                $fileExt = 'jpg';
                break;
            case 'image/png':
                $fileExt = 'png';
                break;
            case 'image/gif':
                $fileExt = 'gif';
                break;
        }
        return $this->fileExtName = $fileExt;
    }

    public function setBasePath($path)
    {
        $this->basePath = $path;
        return $this;
    }

    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    public function setFileName($filename)
    {
        $this->fileName = $filename;
        return $this;
    }

    public function getFileName()
    {

        return $this->fileName;
    }

    /**获取图片真实名米
     * @return mixed
     */
    public function getname()
    {
        return $this->name;
    }

    public function setExtName($fileExtName)
    {
        $this->fileExtName = $fileExtName;
        return $this;
    }


    public function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct)
    {
        $opacity = $pct;
        $cut = imagecreatetruecolor($src_w, $src_h);
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        $opacity = 100 - $opacity;
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $opacity);
    }


    /**
     * 添加水印
     * @param $dst_im  图片临时路径
     * @param $url     图片保存位置
     * @return bool
     */
    public function copyimg($dst_im, $url)
    {
        $dst_info = getimagesize($dst_im);
        $dst_im = file_get_contents($dst_im);
        $dst_im = imagecreatefromstring($dst_im);
        $src = __DIR__ . '/../../public/img/copy.png'; //水印图片
        $src_im = imagecreatefrompng($src);
        $src_info = getimagesize($src);
        $alpha = 50;
        if ($dst_info[0] > ($src_info[0] + 20) and $dst_info[1] > ($src_info[1] + 20)) {
            $this->imagecopymerge_alpha($dst_im, $src_im, $dst_info[0] - $src_info[0] - 20, $dst_info[1] - $src_info[1] - 20, 0, 0, $src_info[0], $src_info[1], $alpha);
        }
        //保存到指定目录
        imagejpeg($dst_im, $url,100);
        imagedestroy($dst_im);
        imagedestroy($src_im);
        return true;
    }

    /*
     * @param string $path 上传文件存储路径
     */
    public function save($is_watermark = false)
    {
        if (empty($this->path)) {
            $this->path = '/uploads/' . date('Y') . '/' . date('m') . '/' . date('d');
        }
        if (empty($this->fileName)) {
            $this->fileName = md5(time() . $this->fileTempName);
        }
        $savePath = $this->basePath . $this->path;
        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }
        if ($is_watermark) {
            $this->copyimg($this->fileTempName, $savePath . '/' . $this->fileName . '.' . $this->getFileExtName());
        } else {
            move_uploaded_file($this->fileTempName, $savePath . '/' . $this->fileName . '.' . $this->getFileExtName());
        }

        return $this->path . '/' . $this->fileName . '.' . $this->getFileExtName();
    }


}
