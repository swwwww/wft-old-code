<?php

namespace Deyi\GetCacheData;

use Application\Module;
use Deyi\BaseController;
use library\Service\System\Cache\RedCache;

class CityCache
{
    use BaseController;

    private static $_instance = null;

    private function getServiceLocator()
    {
        return Module::$serviceManager;
    }


    /**
     *  获取城市
     * @param $coupon_id
     * @return array
     */
    private function _getCitys()
    {
//        $key = "D:citys";
//        $cache_data = RedCache::get($key);
//        if ($cache_data !== false) {
//            return json_decode($cache_data, true);
//        }
//
//        $city = $this->getAllCities();
//
//        $status = RedCache::setnx($key, json_encode($city, JSON_UNESCAPED_UNICODE), 604800); // 缓存7日
//
//        if ($status) {
//            return $city;
//        } else {
//            $cache_data = RedCache::get($key);
//
//            return json_decode($cache_data, true);
//        }
        return RedCache::fromCacheData("D:citys",function(){
            return  $this->getAllCities();
        },604800,true);

    }

    /**
     * 返回城市选择菜单
     * @param $city
     * @param $only 0只有地方 1主站 2全部 3主站＋全部
     * @return string
     */
    private function _getFilterCity($city, $only,$auto)
    {

        $cities = $this->getAllCities($only);

        $script = '<script type="text/javascript">
        function getQueryString(name)
        {
            if(location.href.indexOf("?")==-1)
            {
                window.location.href = location.href+"?city="+name;
                return false;
            }

            var urls = location.href.split("?");
            urls = (urls[0]);

            var queryString = location.href.substring(location.href.indexOf("?")+1);
            queryString = queryString.replace(/\?/g, "&");
            var parameters = queryString.split("&");
            var pos, paraName, paraValue;
            var link = "";
            for(var i=0; i<parameters.length; i++)
            {
                pos = parameters[i].indexOf("=");
                if(pos == -1) { continue; }

                paraName = parameters[i].substring(0, pos);
                paraValue = parameters[i].substring(pos + 1);

                if(paraName == "city")
                {
                    continue;
                }
                link += ("&"+paraName+"="+paraValue);
            }

            if(link){
                window.location.href = urls+"?"+link+"&city="+name;
            }else{
                window.location.href = urls+link+"?&city="+name;
            }
            return false;

        };

    </script>';
        if($auto){
            $str = '<select name="city" onchange="getQueryString(this.options[this.selectedIndex].value);" >';
        }else{
            $str = '<select name="city" >';
        }
//0只有地方 1主站 2全部 3主站＋全部
        if ($only==2||$only==3) {
            $str .= '<option value="0" '.(($city==0)?"selected":"").' >全部</option>';
        }

        foreach ($cities as $k => $c) {
            if ($k == $city) {
                $str .= '<option value="' . $k . '" selected >' . $c . '</option>';
            } else {
                $str .= '<option value="' . $k . '" >' . $c . '</option>';
            }
        }

        $str .= '</select>';
        $str = $script . $str;

        if ($this->getAdminCity() != 1) {
            $str = $cities[$this->getAdminCity()];
        }

        return $str;
    }

    /**
     *  获取所有站点信息
     * @return array
     */
    public static function getCities()
    {
        $tag = self::_getInstance()->_getCitys();
        if (!$tag) {
            $tag = [];
        }

        return $tag;
    }

    public static function getFilterCity($city, $only = 0,$auto = 1)
    {
        $tag = self::_getInstance()->_getFilterCity($city, $only,$auto);
        if (!$tag) {
            $tag = '';
        }

        return $tag;
    }

    private static function _getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new CityCache();
        }

        return static::$_instance;
    }

}



