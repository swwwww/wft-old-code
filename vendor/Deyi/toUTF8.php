<?php
namespace Deyi;
class toUTF8
{

    public static function fromGBK($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'GBK');

    }

    public static function from($str)
    {
        return mb_convert_encoding($str, 'UTF-8', 'GBK');

    }

	/**
	 * @param $arr
	 * @return mixed
	 * 增加了对数组GBK数据的转换
	 */
	public static function fromArray(&$arr) {
		foreach($arr as $k => $a) {
			if(	gettype($a) == 'array' ) {
				$arr[$k] = toUTF8::fromArray($arr[$k]);
			} else if(gettype($a) == 'string') {
				$arr[$k] = mb_convert_encoding($a, 'UTF-8', 'GBK');
			}
		}
		return $arr;
	}
}