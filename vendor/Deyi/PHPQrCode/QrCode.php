<?php

namespace Deyi\PHPQrCode;
require_once("qrlib.php");


class QrCode
{


    static function png($code,$outfile = false, $size = 5, $margin = 3)
    {
        // outputs image directly into browser, as PNG stream
        \QRcode::png($code, $outfile, $level = QR_ECLEVEL_L, $size, $margin, $saveandprint = false);
    }


    static function img($code, $outfile, $level = QR_ECLEVEL_L, $size, $margin)
    {
        // outputs image directly into browser, as PNG stream
        \QRcode::png($code, $outfile, $level, $size, $margin, $saveandprint = false);
    }

}
