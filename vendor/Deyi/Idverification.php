<?php

namespace Deyi;

/**
 * 身份证处理类
 */
class Idverification
{

    //检证身份证是否正确
    public static function isCard($card)
    {
        $card = self::to18Card($card);
        if (strlen($card) != 18) {
            return false;
        }
        $cardBase = substr($card, 0, 17);
        $v = (self::getVerifyNum($cardBase) == strtoupper(substr($card, 17, 1)));


        return array('errNum' => $v ? 0 : 1, 'retData' => array('birthday' => $v ? self::getIDCardInfo($card) : '', 'sex' => $v ? self::getSex($card) : ''));
    }

    private static function getIDCardInfo($IDCard, $format = 1)
    {

        $tdate = '';//生日，格式如：2012-11-15

        if (strlen($IDCard) == 18) {
            $tyear = intval(substr($IDCard, 6, 4));
            $tmonth = intval(substr($IDCard, 10, 2));
            $tday = intval(substr($IDCard, 12, 2));
        } elseif (strlen($IDCard) == 15) {
            $tyear = intval("19" . substr($IDCard, 6, 2));
            $tmonth = intval(substr($IDCard, 8, 2));
            $tday = intval(substr($IDCard, 10, 2));
        }

        if ($tyear > date("Y") || $tyear < (date("Y") - 100)) {
            $flag = 0;
        } elseif ($tmonth < 0 || $tmonth > 12) {
            $flag = 0;
        } elseif ($tday < 0 || $tday > 31) {
            $flag = 0;
        } else {
            if ($format) {
                $tdate = $tyear . "-" . $tmonth . "-" . $tday;
            } else {
                $tdate = $tmonth . "-" . $tday;
            }


        }
        return $tdate;
    }

    //格式化15位身份证号码为18位
    public static function to18Card($card)
    {
        $card = trim($card);

        if (strlen($card) == 18) {
            return $card;
        }

        if (strlen($card) != 15) {
            return false;
        }

        // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
        if (array_search(substr($card, 12, 3), array('996', '997', '998', '999')) !== false) {
            $card = substr($card, 0, 6) . '18' . substr($card, 6, 9);
        } else {
            $card = substr($card, 0, 6) . '19' . substr($card, 6, 9);
        }
        $card = $card . self::getVerifyNum($card);
        return $card;
    }

    // 计算身份证校验码，根据国家标准gb 11643-1999
    private static function getVerifyNum($cardBase)
    {
        if (strlen($cardBase) != 17) {
            return false;
        }
        // 加权因子
        $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);

        // 校验码对应值
        $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');

        $checksum = 0;
        for ($i = 0; $i < strlen($cardBase); $i++) {
            $checksum += substr($cardBase, $i, 1) * $factor[$i];
        }

        $mod = $checksum % 11;
        $verify_number = $verify_number_list[$mod];

        return $verify_number;
    }

    //根据身份证号，自动返回性别
    private static function getSex($cid)
    {
        //M 男
        $sexint = (int)substr($cid, 16, 1);
        return $sexint % 2 === 0 ? 'W' : 'M';
    }
}
