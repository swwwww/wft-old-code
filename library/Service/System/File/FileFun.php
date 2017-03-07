<?php

namespace library\Service\System\File;

/**
 * 文件操作类
 * Class File
 * @package library\Fun
 */
class FileFun {

    /**
     * 创建目录
     * @param $dir
     * @return bool
     * @throws \Exception
     */
    public static function createDir($dir)
    {
        if(!$dir){
            throw new \Exception("目录不能为空");
        }
        return is_dir($dir) or (self::createDir(dirname($dir)) and @mkdir($dir, 0755));
    }
}