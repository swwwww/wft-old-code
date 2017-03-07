<?php

namespace library\Service\Kidsplay;

use library\Fun\M;

class Shop {
    public static function getShopListForExcercise ($param, $start = 0, $limit = 10, $flag_count = 0) {
        $pdo = M::getAdapter();
        $sql_where = '';

        if ($param['bid']) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }

            if (is_array($param['bid'])) {
                $data_temp_bid = implode(',', $param['bid']);
                $sql_where .= " play_excercise_shop.bid in ({$data_temp_bid}) ";
            } else {
                $sql_where .= " play_excercise_shop.bid = " . $param['bid'];
            }
        }

        if ($param['is_close'] === 0 || $param['is_close'] === 1) {
            if (!empty($sql_where)) {
                $sql_where .= " AND ";
            }

            $sql_where .= " play_excercise_shop.is_close = " . $param['is_close'];
        }

        if ($flag_count == 1) {
            $sql_count = "
                      SELECT 
                          play_shop.shop_id,
                          play_shop.shop_city,
                          play_shop.shop_name,
                          play_shop.busniess_circle,
                          play_excercise_shop.bid
                      FROM play_excercise_shop 
                      LEFT JOIN play_shop ON play_excercise_shop.shopid = play_shop.shop_id 
                      WHERE {$sql_where} 
                     ";

            $data_count = $pdo->query($sql_count, array())->count();

            return $data_count;
        } else {
            $sql = "
                SELECT 
                    play_shop.shop_id,
                    play_shop.shop_city,
                    play_shop.shop_name,
                    play_shop.busniess_circle,
                    play_excercise_shop.bid
                FROM play_excercise_shop 
                LEFT JOIN play_shop ON play_excercise_shop.shopid = play_shop.shop_id 
                WHERE
                    {$sql_where}
                LIMIT {$start}, {$limit}
               ";
            $data_return = $pdo->query($sql, array())->toArray();

            return $data_return;
        }
    }
}