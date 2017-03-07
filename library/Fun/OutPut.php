<?php
namespace library\Fun;

class OutPut
{

    public static function out($file_name, $head, $content) {
        ob_end_clean();
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment;filename='. $file_name);
        header('Cache-Control: max-age=0');

        // 打开PHP文件句柄，php://output 表示直接输出到浏览器
        $fp = fopen('php://output', 'a');

        //转码 否则 excel 会乱码
        foreach ($head as $i => $row) {
            $head[$i] = mb_convert_encoding($row,'CP936','UTF-8');
        }

        fputcsv($fp, $head);

        foreach ($content as $tent) {
            $outData = array();
            foreach ($tent as $k=>$v) {
                array_push($outData, mb_convert_encoding($v,'CP936','UTF-8'));
            }
            fputcsv($fp, $outData);
        }
        exit;
    }


}