<?php
namespace Deyi;
class toGBK
{
    public static function from($str)
    {
        return mb_convert_encoding($str, 'GBK', 'UTF-8');
    }
}