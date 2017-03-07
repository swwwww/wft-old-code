<?php
namespace library\Service\System;

use library\Service\System\File\FileFun;
use library\Service\ServiceManager;

class Logger
{

    static private $loggers = array();

    //写入普通日志
    static public function writeLog($data)
    {
        $dir = './log';
        $file = date('Y-m-d') . '.log';
        self::Logger($dir, $file)->crit(self::toString($data));
    }

    //写入错误日志
    static public function WriteErrorLog($data)
    {
        $dir = './log';
        $file = date('Y-m-d') . '-error.log';
        self::Logger($dir, $file)->err(self::toString($data));
    }

    static private function toString($data)
    {
        if (is_array($data) or is_object($data)) {
            return print_r($data);
        } else {
            return $data;
        }
    }

    static private function Logger($dir, $file)
    {
        $file = $dir . '/' . $file;
        if (isset(self::$loggers[$file])) {
            return self::$loggers[$file];
        } else {
            FileFun::createDir($dir);
            $logger = new \Zend\Log\Logger;
            $writer = new \Zend\Log\Writer\Stream($file);
            $logger->addWriter($writer);
            self::$loggers[$file] = $logger;
            return $logger;
        }
    }

}