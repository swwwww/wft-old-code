<?php
namespace Deyi;
use library\Fun\Common;
use library\Service\System\Logger;

class WriteLog
{


    /**
     * @param $log string|array|object $log
     * @param bool $up 是否线上才写日志
     * @return int
     */
    public static function WriteLog($log, $up = false)
    {
        if (is_array($log) or is_object($log)) {
            $log = print_r($log, true);
        }
        if ($up) {
            return Logger::writeLog($log);
        } else {
            if (!self::isUp()) {
                return Logger::writeLog($log);
            }
        }
    }

    //是否线上服务器
    public static function isUp()
    {
        //不是线上的服务地址
        return Common::isUp();
    }

//    private static function setLog($log)
//    {
//        $dir = self::getDir();
//        $filename = self::getFileName();
//        self::createDir($dir);
//        $log = date('Y-m-d H:i:s :') . "\n{$log}\n=======================================";
//        $logger = new \Zend\Log\Logger;
//        $writer = new \Zend\Log\Writer\Stream($dir . $filename);
//        $logger->addWriter($writer);
//        $logger->crit($log);
//
//       // return file_put_contents($dir . $filename, $log . "\n", FILE_APPEND);
//    }

    private static function createDir($dir)
    {
        return is_dir($dir) or (self::createDir(dirname($dir)) and @mkdir($dir, 0777));
    }

    private static function getDir()
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/../log/';
    }

    private static function getFileName()
    {
        return date('Y-m-d') . '.log';
    }

    /**
     * 获取文件最后N行
     * @param $file
     * @param int $line
     * @return bool|string
     */
    private static function getLastLines($file, $line = 1)
    {
        if (!$fp = fopen($file, 'r')) {
            return false;
        }
        $pos = -1;      //偏移量
        $eof = " ";     //行尾标识
        $data = "";
        while ($line > 0) {//逐行遍历
            while ($eof != "\n" && $eof !== false) { //不是行尾
                fseek($fp, $pos, SEEK_END);//fseek成功返回0，失败返回-1
                $eof = fgetc($fp);//读取一个字符并赋给行尾标识
                $pos--;//向前偏移
            }
            if ($eof === false) { //到达文件头 取出当前行并拼接 data
                $pos += 2;
                fseek($fp, $pos, SEEK_END);
                $data = fgets($fp) . $data;//读取一行
                break;
            } else {
                $eof = " ";
                $data = fgets($fp) . $data;//读取一行
                $line--;
            }
        }
        fclose($fp);
        return $data;
    }

    /**
     * 获取日志最后N行
     * @param int $line
     * @return bool|string
     */
    public static function getLogLastLines($line = 1000)
    {
        return self::getLastLines(self::getDir() . self::getFileName(), $line);
    }


}