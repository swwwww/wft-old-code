<?php
    $client = new swoole_client(SWOOLE_SOCK_TCP);
    if (!$client->connect('127.0.0.1', 9501, 1)) {
        exit("connect failed. Error: {$client->errCode}\n");
    }
    $data = array('action' => 'phoneMessage', 'params' => array('phone'=>15994225894,'content'=>'test'));

    $client->send(json_encode($data));

    echo $client->recv();
    $client->close();
