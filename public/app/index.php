<?php


$android_url = 'http://wan.wanfantian.com/download/wft.apk';
$ios_url = 'https://itunes.apple.com/cn/app/de-yi-sheng-huo-lun-tan/id950652997?mt=8';


//todo 接受活动id
$aid = isset($_GET['ac']) ? $_GET['ac'] : 0;


if ($aid) {
    $android_url = 'http://wft.deyi.com/download/wft_' . $aid . '.apk';
    $ios_url = 'https://itunes.apple.com/app/apple-store/id950652997?pt=1526579&ct=' . $aid . '&mt=8';  //ct 推广id
}



if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'micromessenger') === false) {

} else {
    // 跳转到应用宝
    $bao = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.deyi.wanfantian&g_f=991653';
    header("Location: $bao", true);
    exit;
}







?>

<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width; initial-scale=1.0; minimum-scale=1.0; maximum-scale=1.0">
    <title>玩翻天手机APP下载</title>
    <style>
        #zz {
            width: 100%;
            height: 100%;
            background: #000;
            position: fixed;
            filter: alpha(opacity=90);
            -moz-opacity: 0.9;
            opacity: 0.9;
            text-align: right;
        }

        #zz img {
            width: 100%;
            height: auto;
        }

        #loading {
            text-align: center;
        }

        #loading span {
            font-size: 20px;
            font-weight: bold;
        }
    </style>
    <link href="images/appm.css" rel="stylesheet" type="text/css">
    <script>
        if (navigator.userAgent.match(/micromessenger/i)) {
            document.write("<div id='zz'><img src='images/zz.png'></div>");
        } else {
            if (navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iphone os/i) || navigator.userAgent.match(/ipod/i)) {
                window.location.href = "<?php echo $ios_url?>";
            } else if (navigator.userAgent.match(/android/i)) {
                window.location.href = "<?php echo $android_url?>";
            }
        }
    </script>
</head>
<body>
<div id="loading">
    <span>玩翻天谢谢你,正在为你安装手机APP......</span><br>
    如未弹出安装确认窗口,请点击以下链接,手动安装.
</div>
<div class="article">
    <dl class="iosbox">
        <dt>IOS用户下载</dt>
        <dd>
            <p>
                <!--                <a href="itms-services://?action=download-manifest&url=https://api.deyi.com/wft.plist"-->
                <!--                   class="waplink"><img src="images/app_r9_c8.png" width="100" height="45"></a>-->
                <a href="<?php echo $ios_url; ?>"
                   class="waplink"><img src="images/app_r9_c8.png" width="100" height="45"></a>
            </p>
        </dd>
    </dl>
    <dl class="androbox">
        <dt>安卓用户下载</dt>
        <dd>
            <p>
                <a href="<?php echo $android_url ?>" class="androdownload" target="_blank"><img
                        src="images/app_r9_c8.png"></a>
            </p>
        </dd>
    </dl>
</div>
</body>
</html>