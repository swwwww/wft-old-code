<?php

$common_arr = include_once(__DIR__ . '/../config/server.config.php');
$db = new PDO($common_arr['db']['dsn'], $common_arr['db']['username'], $common_arr['db']['password'],
    $common_arr['db']['driver_options']);


$mongo = new MongoClient(); // 连接

$collection = $mongo->selectDB('wft')->selectCollection('near_by');

//$collection->remove(['type' => 0]);

$last_id = 0;

while (1) {

    $rs = $db->query("SELECT * FROM wft.play_shop WHERE shop_id > {$last_id} order by shop_id asc limit 500",
        PDO::FETCH_ASSOC);

    if(false === $rs || $rs->rowCount()  < 1 ){
        break;
    }

    foreach ($rs as $row) {

        //中国范围内
        if ((int)$row['addr_x'] > 140 || (int)$row['addr_x'] < 70) {
            continue;
        }
        if ((int)$row['addr_y'] > 55 || (int)$row['addr_y'] < 15) {
            continue;
        }

        $row['addr'] = array(
            'type' => 'Point',
            'coordinates' => array((float)$row['addr_x'], (float)$row['addr_y'])
        );
        $row['type'] = 0;
        $row['city'] = $row['shop_city'];
        $row['shop_id'] = array_key_exists('shop_id', $row) ? $row['shop_id'] : '0';
        $row['title'] = $row['shop_name'];
        $row['address'] = $row['shop_address'];
        try{
            $last_id = $row['shop_id'];
            $collection->update(
                array('shop_id' => $row['shop_id']),
                array('$set' => $row),
                array('upsert' => true) );

        }catch(Exception $e) {
            $last_id = $row['shop_id'];
            continue;
        }

    }
    $collection->ensureIndex(array('addr' => '2dsphere'));

}

$rs=null;
$collection = null;

echo "ok";
