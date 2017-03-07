<?php

/**
 *
 * 注意：
 * 1.客户端和服务端都需要正确关闭链接
 * 2.设置ulimit参数为100000或更大
 *
 * Class Server
 */
class Server
{
    private $serv;

    public function __construct()
    {
        ini_set("date.timezone", "PRC");

        $this->serv = new swoole_server("127.0.0.1", 9501);
        $this->serv->set(array(
            'daemonize' => true,  //是否守护进程后台执行
            'log_file' => './swoole.log',  //结果日志，守护运行时配置
            'debug_mode' => 0,
            'dispatch_mode' => 2,  //1轮循模式,2固定模式，根据连接的文件描述符分配worker
            'worker_num' => 8,  //每个worker大概40M内存 cpu1~4倍最合理
            'task_worker_num' => 10,  //配置task进程的数量,并发数
            'max_conn' => 1000,    //此参数用来设置Server最大允许维持多少个tcp连接。超过此数量后，新进入的连接将被拒绝。
            'max_request' => 1000,  //设置worker进程的最大任务数。一个worker进程在处理完超过此数值的任务后将自动退出。这个参数是为了防止PHP进程内存溢出，异步不需要设置
            'task_max_request' => 1000,  //设置task进程的最大任务数 防止PHP进程内存溢出

            'task_ipc_mode' => 3,   //设置task进程与worker进程之间通信的方式。设置为争抢模式3
            // 'heartbeat_check_interval' => 5, //启用心跳检测 每5秒遍历一次
            // 'heartbeat_idle_time' => 6,//超过6秒 断开链接

        ));
        $this->serv->on('Start', array($this, 'onStart'));
        $this->serv->on('Connect', array($this, 'onConnect'));
        $this->serv->on('Receive', array($this, 'onReceive'));
        $this->serv->on('Close', array($this, 'onClose'));
        // bind callback
        $this->serv->on('Task', array($this, 'onTask'));
        $this->serv->on('Finish', array($this, 'onFinish'));

        $this->serv->on('WorkerStart', array($this, 'onWorkerStart'));


        $this->serv->start();
    }


    //$fd 客户端链接id
    //$from_id  Worker id
    //$task_id  task id
    public function onStart($serv)
    {
        echo "Start\n";
    }

    public function onWorkerStart($serv, $worker_id)
    {
        include_once 'PhoneMessage/PhoneMessage.php';
        include_once 'GeTuiMessage/GeTui.php';
    }

    public function onConnect($serv, $fd, $from_id)
    {
//        echo "Client {$fd} connect\n";
    }

    public function onClose($serv, $fd, $from_id)
    {
        // echo "Client {$fd} close connection\n";
    }


    /**
     * @param swoole_server $serv
     * @param $fd
     * @param $from_id
     * @param $data |json   array('action'=>1,'params'=>'xxxxxxxxx')
     */
    public function onReceive(swoole_server $serv, $fd, $from_id, $data)
    {
        $requestData = json_decode($data, true);


        //todo 判断是否调用task
        $requestData['fd'] = $fd;
        $serv->send($requestData['fd'], "server:接收到任务\n");  //告诉客户端开始任务必须
        $serv->close($requestData['fd']);
        $serv->task($requestData);
    }


    public function onTask($serv, $task_id, $from_id, $requestData)
    {
        echo date('Y-m-d H:i:s') . ":收到请求[{$requestData['fd']}]" . json_encode($requestData, JSON_UNESCAPED_UNICODE) . "\n";

        //echo "连接id：{$requestData['fd']}，worker_id:{$from_id},task_id:{$task_id}\n";


        $content = '';
        switch ($requestData['action']) {
            case 'insertOrder':

                break;
            case 'phoneMessage': //发送短信
                $content = PhoneMessage::Send($requestData['params']['phone'], $requestData['params']['content']);
                break;
            case 'geTuiMessageToOne': //对单个用户推送
                $getui = new GeTui();
                $s = $getui->pushMessageToSingle(
                    $requestData['params']['cid'],
                    $requestData['params']['title'],
                    $requestData['params']['content']
                );

                return json_encode($s, JSON_UNESCAPED_UNICODE) . "\n";
                break;
            case 'geTuiMessageToAll': //对所有用户推送
                $getui = new GeTui();
                $s = $getui->pushMessageToApp(
                    $requestData['params']['title'],
                    $requestData['params']['content'],
                    $requestData['params']['task'],
                    $requestData['params']['duration'],
                    $requestData['params']['city']
                ); //return bool
                return json_encode($s, JSON_UNESCAPED_UNICODE) . "\n";
                break;
            case 'geTuiMessageStoptask':
                $getui = new GeTui();
                $s = $getui->stoptask($requestData['params']['task_id']); //return bool
                return $s ? '成功' : '失败';
                break;
            default:
                return "操作不存在\n";
        }

        return $content;

    }

    public function onFinish($serv, $task_id, $data)
    {
        echo date('Y-m-d H:i:s') . "运行结果[{$task_id}]:" . $data;
    }
}

$server = new Server();
