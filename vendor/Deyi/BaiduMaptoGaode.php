<?php
namespace Deyi;
class BaiduMaptoGaode
{


    static $PI = 3.141592653;

    /**
     *
     * 百度坐标（BD-09）转 火星/高德（GCJ-02） 坐标
     * @param $bd_lon // 经度 x   114
     * @param $bd_lat // 纬度 y    30
     * @return string;
     */
    public static function bd_decrypt($bd_lon, $bd_lat)
    {

        $x = $bd_lon - 0.0065;
        $y = $bd_lat - 0.006;
        $z = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * self::$PI);
        $theta = atan2($y, $x) - 0.000003 * cos($x * self::$PI);
        $gg_lon = $z * cos($theta);
        $gg_lat = $z * sin($theta);
        return $gg_lon . ',' . $gg_lat;
    }


    /**
     * 火星/高德（GCJ-02） 坐标 转百度坐标（BD-09）
     * @param $gg_lat
     * @param $gg_lon
     * @return string
     */
    public static function bd_encrypt($gg_lat, $gg_lon)
    {
        $x = $gg_lon;
        $y = $gg_lat;
        $z = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * self::$PI);
        $theta = atan2($y, $x) + 0.000003 * cos($x * self::$PI);
        $bd_lon = $z * cos($theta) + 0.0065;
        $bd_lat = $z * sin($theta) + 0.006;
        return $bd_lon . ',' . $bd_lat;
    }


}