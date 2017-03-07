<?php

//unset($_GET['debug']);  //TODO 是否关闭调试
if (isset($_GET['debug']) and $_GET['debug'] == 1) {
    error_reporting(-1);
    ini_set('display_errors', '0');
} else {
    error_reporting(-1);
    ini_set('display_errors', '0');
}
//die;
//获取不同版本的API 接口
$ver = (int)isset($_SERVER['HTTP_VER']) ? $_SERVER['HTTP_VER'] : 1;
define("VER", 'V' . $ver);  //版本号
define("RUN_T1", microtime(true)); //程序开始运行时间
define("ACCESSLOG", true); //是否记录日志

header("Content-type: text/html; charset=utf-8");

/******************* 老版本结束支持 *************************/
if (isset($_SERVER['HTTP_VER']) && $ver <= 9) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type:application/json;charset=UTF-8');
    echo '{"error_code": 0, "error_msg": "请升级到最新版，享受更多优惠哦~~"}';
    exit;
}
/******************* 老版本结束支持 *************************/

if (!is_dir(__DIR__ .'/../V' . $ver)) {
    header("Content-type: text/html; charset=utf-8");
    header("Status: 404 Not Found");
    exit('<h1>404 Folder Not Found</h1>');
}

chdir(dirname(__DIR__));
require 'init_autoloader.php';
$application = Zend\Mvc\Application::init(require VER . '/config/application.config.php');
\library\Service\ServiceManager::setManager($application);
$application->run();


