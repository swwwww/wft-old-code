<?php

namespace Deyi;

use Zend\Stdlib\ArrayObject;

class getServerConfig
{

    static private $serverConfig;


    /**
     * 获取服务器相关配置
     * @param $keyName
     * @return Array
     */
    static function get($keyName = null)
    {
        if (self::$serverConfig === NULL) {
            self::$serverConfig = require(__DIR__ . '/../../config/server.config.php');
        } else {
            self::$serverConfig;
        }

        if ($keyName === null) {
            return self::$serverConfig;
        } else {
            return self::$serverConfig[$keyName];
        }
    }

}