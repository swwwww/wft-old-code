<?php

namespace Deyi\Consult;

use Application\Module;
use Deyi\BaseController;
use Deyi\GeTui\GeTui;

class Push
{
    use BaseController;

    //BaseController 使用
    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }

    function consult_push($to_uid, $to_uid_token, $title, $type, $id)
    {
        $info = '';
        $content = array(
            'title' => htmlspecialchars_decode($title, ENT_QUOTES),
            'info' => htmlspecialchars_decode($info, ENT_QUOTES),
            'type' => (int)$type,
            'id' => (int)$id,
            'time' => time(),
        );
        $geTui = new GeTui();
        $str = substr($to_uid_token, 0, 10);
        $res = $geTui->push($to_uid . '__' . $str, htmlspecialchars_decode($title, ENT_QUOTES), json_encode($content, JSON_UNESCAPED_UNICODE));
        if ($res['result'] === 'ok') {
            return true;
        } else {
            return false;
        }
    }
}



