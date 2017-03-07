<?php

namespace Deyi\GeTui;

class GeTui
{

    public function pushSwoole($data = array())
    {
        $client = new \swoole_client(SWOOLE_SOCK_TCP);
        if (!$client->connect('127.0.0.1', 9501, 1)) {
//            echo "connect failed. Error: {$client->errCode}\n";
            return '服务异常';
        } else {
            $client->send(json_encode($data));
            $msg = $client->recv();
            $client->close();
            return $msg;
        }
    }

    public function Push($cid, $title, $content)
    {
        $this->pushSwoole(array('action' => 'geTuiMessageToOne', 'params' => array('cid' => $cid, 'title' => $title, 'content' => $content)));
    }

    //群推接口
    /**
     * @param $title
     * @param $content
     * @param $task
     * @param $duration //持续时间
     * @param $city //城市编号
     * @return mixed|null
     */
    function pushMessageToApp($title, $content, $task, $duration = 43200000, $city)
    {
        $this->pushSwoole(array('action' => 'geTuiMessageToAll', 'params' => array('title' => $title, 'content' => $content, 'task' => $task, 'duration' => $duration, 'city' => $city)));
    }


    //推送任务停止
    /**
     * @param $task_id
     * @return bool
     */
    function stoptask($task_id)
    {

        $this->pushSwoole(array('action' => 'geTuiMessageStoptask', 'params' => array('task_id' => $task_id)));
    }

}



