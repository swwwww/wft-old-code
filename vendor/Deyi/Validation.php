<?php

namespace Deyi;


class Validation {


    /**
     * 随机生成字母数字字符串
     * @param int $len
     * @return string
     */
    public function rand_gen_code($len = 6)
    {
        $a = str_shuffle('abcdefghjkmnpqrstuvwxyz2356789');
        $s = '';
        $slen = strlen($a);
        for ($i = 0; $i < $len; ++$i) {
            $k = rand(0, $slen - 1);
            $s .= $a[$k];
        }
        return $s;
    }

    //随机生成支付密码
    public function rand_pay_code($len = 6){
        /* 选择一个随机的方案 */
        return str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 检测字符串长度范围
     * @param $string
     * @param int $min
     * @param int $max
     * @param string $stringName
     * @return null|string
     */
    public static function strLengthRange($string, $min = 1, $max = 0, $stringName = '')
    {
        $strLen = mb_strlen($string, 'UTF8');
        if ($strLen < $min) {
            return "{$stringName} 不能少于 {$min}个字符";
        }
        if ($min <= $max && $max < $strLen) {
            return "{$stringName} 不能超过 {$max}个字符";
        }

        return NULL;
    }

    /**
     * 批量检测字符长度范围
     * @param $data   array('value' => string, 'min' => num, 'max' => num, 'name' => '提示名称')
     * @return null|string
     */
    public function batchLenthRange($data) {
        $result = NULL;
        if (is_array($data) && count($data) > 0) {
            foreach($data as $k => $val) {
                $result = $this->strLengthRange($val['value'], $val['min'], $val['max'], $val['name']);
                if ($result !== NULL) {
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * 验证是否是不能为空
     *
     * @param mixed $value 待判断的数据
     * @return boolean 如果为空则返回false,不为空返回true
     */
    public static function isRequired($value) {
        return $value !== NULL &&  rtrim(rtrim($value),'　') !== '';
    }

    /**
     * 手机号检测
     * @param $mobile
     * @return bool
     */
    public static function isMobile($mobile) {
        return 0 < preg_match('/^(13|15|18|17)\d{9}$/', $mobile);
    }


    /**
     * 验证是否是数字及-
     *
     * @param string $string 待验证的字串
     * @return boolean 如果是则返回true，否则返回false
     */
    public static function isUnderlineAndNumber($string) {
        return 0 < preg_match('/^[0-9\-]+$/', $string);
    }

    /**
     * 400电话格式检测
     * @param $phone
     * @return bool
     */
    public static function isServicePhone($phone) {
        return 0 < preg_match('/^400-([0-9]){1}([0-9-]{7})$/', $phone);
    }

    /**
     * 验证是否是合法的email
     *
     * @param string $string 待验证的字串
     * @return boolean 如果是email则返回true，否则返回false
     */
    public static function isEmail($string) {
        return 0 < preg_match("/^\w+(?:[-+.']\w+)*@\w+(?:[-.]\w+)*\.\w+(?:[-.]\w+)*$/", $string);
    }

    public static function isQQ($qq) {
        return 0 < preg_match('/^\d{4,15}$/', $qq);
    }

    /**
     * 验证是否是合法的url
     *
     * @param string $string 待验证的字串
     * @return boolean 如果是合法的url则返回true，否则返回false
     */
    public static function isUrl($string) {
        return false !== filter_var($string, FILTER_VALIDATE_URL);
    }

    /**
     * 验证是否有中文
     *
     * @param string $string  被搜索的 字符串
     * @param array $matches  会被搜索的结果,默认为array()
     * @param boolean $ifAll  是否进行全局正则表达式匹配，默认为false即仅进行一次匹配
     * @return boolean 如果匹配成功返回true，否则返回false
     */
    public static function hasChinese($string, &$matches = array(), $ifAll = false) {
        return 0 < self::validateByRegExp('/[\x{4e00}-\x{9fa5}]+/u', $string, $matches, $ifAll);
    }

    /**
     * 验证是否是中文
     *
     * @param string $string 待验证的字串
     * @return boolean 如果是中文则返回true，否则返回false
     */
    public static function isChinese($string) {
        return 0 < preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $string);
    }

    /**
     * 验证是否是字母和数字及-
     *
     * @param string $string 待验证的字串
     * @return boolean 如果是中文则返回true，否则返回false
     */
    public static function isCharAndNumber($string) {
        return 0 < preg_match('/^[A-Za-z0-9\-]+$/', $string);
    }

    /**
     * 在 $string 字符串中搜索与 $regExp 给出的正则表达式相匹配的内容。
     *
     * @param string $regExp  搜索的规则(正则)
     * @param string $string  被搜索的 字符串
     * @param array $matches 会被搜索的结果，默认为array()
     * @param boolean $ifAll   是否进行全局正则表达式匹配，默认为false不进行完全匹配
     * @return int 返回匹配的次数
     */
    private static function validateByRegExp($regExp, $string, &$matches = array(), $ifAll = false) {
        return $ifAll ? preg_match_all($regExp, $string, $matches) : preg_match($regExp, $string, $matches);
    }

    /** 验证邮编格式
     * @param $string 待检验的字符串
     * @return bool 如果是邮编格式返回true，否则返回false
     */
    public static function isPostCode($string) {
        return 0 < preg_match('/^[0-9]\d{5}$/',$string);
    }
}