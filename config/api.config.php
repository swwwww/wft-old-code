<?php
return array(
    
    'upload_dir' => '/uploads/' . date('Ym/d/'), //相对于网站根目录 $_SERVER['DOCUMENT_ROOT']

    'TRADE_CLOSED'=>900, //交易过期时间



    'city' => array(
        'WH' => '武汉',
        'CS' => '长沙',
        'NJ' => '南京',
    ),


    'shop_type' => array(
        '0' => '儿童乐园',
        '1' => '烘焙',
        '2' => '农家乐',
        '3' => '展览',
        '4' => '演出机构',
        '5' => '户外教育',
        '6' => '手工',
        '7' => '游乐场',
        '8' => '运动场馆',
        '9' => '景区旅游',
        '10' => '创意教育',
    ),

    'coupon_type' => array(
        '0' => '展览',
        '1' => '手工DIY',
        '2' => '采摘',
        '3' => '演出',
        '4' => '游乐园',
        '5' => '运动',
        '6' => '亲子游',
        '7' => '水上乐园',
        '8' => '益智创意',
    ),
    //卡券使用状态
    'coupon_code_status' => array(
        0 => '未使用',
        1 => '已使用',
        2 => '已退款',
        3 => '退款中'
    ),


    //订单状态  最终状态
    'order_status' => array(
        0 => '未付款',
        1 => '付款中',
        2 => '已付款',
        3 => '退款中',
        4 => '退款成功',
        5 => '已使用',
        6 => '已过期',
        7 => '团购中'
    ),

    //订单类型
    'order_type' => array(
        1 => '普通卡券',
        2 => '活动卡券'
    ),


    //专题类别
    'theme_type' => array(
        1 => '一元手慢无',
        2 => '周末去哪',
        3 => '一般专题',
    ),

    //领券原因类型 领券原因类型 1兑换 2点评商品，３点评游玩地，４活动发放　５商品购买　６采纳攻略　７好评ａｐｐ
    'cash_coupon_type' => array(
        1 => '传播码兑换',
        2 => '点评商品',
        3 => '点评游玩地',
        4 => '活动发放',
        5 => '商品购买',
        6 => '采纳攻略',
        7 => '好评app',
    ),

    //资格券　发放方式 1用户兑换 2邀约 3参加活动 4注册
    'qualify_type' => array(
        1 => '用户兑换',
        2 => '邀约',
        3 => '参加活动',
        4 => '注册'
    ),

    //用户来源
    'user_source' => array(
        'yingyongbao' => '应用宝腾讯开发平台',
        '360' => '360移动开发平台',
        'xiaomi' => '小米开放平台',
        'huawei' => '华为开发者联盟',
        'wandoujia' => '豌豆荚',
        'flyme' => 'Flyme开发平台',
        'lianxiang' => '联想开放平台',
        'baidu' => '百度开放服务平台（安卓&91市场）',
        'oppo' => 'OPPO开发者社区',
        'anzhi' => '安智开发者联盟',
        'jifeng' => '机锋开发者',
        'mumayi' => '木蚂蚁开发者平台',
        'sougou' => '搜狗手机助手开放平台',
        'sanxing' => '三星',
        'leshi' => '乐视',
        'home' => '服务器',
        'ditui' => '地推',
        'weixin' => '微信网页',
        'ios' => 'iOS',
        'H5' => 'H5活动',
    ),

    //版本信息
    'version' => array(
        'old_update' => 0,  //旧版本強制升级
        'version_code' => 28,
        'version_name' => 'v3.3.4',
        'packageurl' => 'http://wan.wanfantian.com/download/wft.apk',
        'versioninfo' => '1.商品支持选择出行日期，下单出行更方便；
        2.修复了一些Bug
'
    ),


    //验证码为 123456
    'user_pass_list' => array(
        "15994225894",
        "13627278349",
        "13265765527",
        "13007124303",
        "13659840537",
        "18696146855",
        "15827025336",
        "13764200025",
        "15071447009",
        "13871281565",
        "15972211849",
        "18696158913",
        "18827660367",
        "15907185637",
        "15927652340",
        "15071256470",
        "13125017690",
        "13037158303"

    ),

//富文本编辑器和其他文件上次路径配置
    'fileConfig' => [ /* 前后端通信相关的配置,注释只允许使用多行方式 */
    /* 上传图片配置项 */
    "imageActionName" => "uploadimage", /* 执行上传图片的action名称 */
    "imageFieldName" => "upfile", /* 提交的图片表单名称 */
    "imageMaxSize" => 2048000, /* 上传大小限制，单位B */
    "imageAllowFiles" => [".png", ".jpg", ".jpeg", ".gif", ".bmp"], /* 上传图片格式显示 */
    "imageCompressEnable" => true, /* 是否压缩图片,默认是true */
    "imageCompressBorder" => 1600, /* 图片压缩最长边限制 */
    "imageInsertAlign" => "none", /* 插入的图片浮动方式 */
    "imageUrlPrefix" => "", /* 图片访问路径前缀 */
    "imagePathFormat" => "/uploads/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    /* {filename} 会替换成原文件名,配置这项需要注意中文乱码问题 */
    /* {rand:6} 会替换成随机数,后面的数字是随机数的位数 */
    /* {time} 会替换成时间戳 */
    /* {yyyy} 会替换成四位年份 */
    /* {yy} 会替换成两位年份 */
    /* {mm} 会替换成两位月份 */
    /* {dd} 会替换成两位日期 */
    /* {hh} 会替换成两位小时 */
    /* {ii} 会替换成两位分钟 */
    /* {ss} 会替换成两位秒 */
    /* 非法字符 \ : * ? " < > | */
    /* 具请体看线上文档: fex.baidu.com/ueditor/#use-format_upload_filename */

    /* 涂鸦图片上传配置项 */
    "scrawlActionName" => "uploadscrawl", /* 执行上传涂鸦的action名称 */
    "scrawlFieldName" => "upfile", /* 提交的图片表单名称 */
    "scrawlPathFormat" => "/uploads/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    "scrawlMaxSize" => 2048000, /* 上传大小限制，单位B */
    "scrawlUrlPrefix" => "", /* 图片访问路径前缀 */
    "scrawlInsertAlign" => "none",

    /* 截图工具上传 */
    "snapscreenActionName" => "uploadimage", /* 执行上传截图的action名称 */
    "snapscreenPathFormat" => "/uploads/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    "snapscreenUrlPrefix" => "", /* 图片访问路径前缀 */
    "snapscreenInsertAlign" => "none", /* 插入的图片浮动方式 */

    /* 抓取远程图片配置 */
    "catcherLocalDomain" => ["127.0.0.1", "localhost", "img.baidu.com"],
    "catcherActionName" => "catchimage", /* 执行抓取远程图片的action名称 */
    "catcherFieldName" => "source", /* 提交的图片列表表单名称 */
    "catcherPathFormat" => "/uploads/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    "catcherUrlPrefix" => "", /* 图片访问路径前缀 */
    "catcherMaxSize" => 2048000, /* 上传大小限制，单位B */
    "catcherAllowFiles" => [".png", ".jpg", ".jpeg", ".gif", ".bmp"], /* 抓取图片格式显示 */

    /* 上传视频配置 */
    "videoActionName" => "uploadvideo", /* 执行上传视频的action名称 */
    "videoFieldName" => "upfile", /* 提交的视频表单名称 */
    "videoPathFormat" => "/uploads/video/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    "videoUrlPrefix" => "", /* 视频访问路径前缀 */
    "videoMaxSize" => 30400000, /* 30M 上传大小限制，单位B，默认102400000 =>100MB */
    "videoAllowFiles" => [
        ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
        ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid"], /* 上传视频格式显示 */

    /* 上传文件配置 */
    "fileActionName" => "uploadfile", /* controller里,执行上传视频的action名称 */
    "fileFieldName" => "upfile", /* 提交的文件表单名称 */
    "filePathFormat" => "/uploads/file/{yyyy}{mm}/{dd}/{time}{rand:6}", /* 上传保存路径,可以自定义保存路径和文件名格式 */
    "fileUrlPrefix" => "", /* 文件访问路径前缀 */
    "fileMaxSize" => 51200000, /* 上传大小限制，单位B，默认50MB */
    "fileAllowFiles" => [
        ".png", ".jpg", ".jpeg", ".gif", ".bmp",
        ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
        ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid",
        ".rar", ".zip", ".tar", ".gz", ".7z", ".bz2", ".cab", ".iso",
        ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".pdf", ".txt", ".md", ".xml"
    ], /* 上传文件格式显示 */

    /* 列出指定目录下的图片 */
    "imageManagerActionName" => "listimage", /* 执行图片管理的action名称 */
    "imageManagerListPath" => "/uploads/", /* 指定要列出图片的目录 */
    "imageManagerListSize" => 20, /* 每次列出文件数量 */
    "imageManagerUrlPrefix" => "", /* 图片访问路径前缀 */
    "imageManagerInsertAlign" => "none", /* 插入的图片浮动方式 */
    "imageManagerAllowFiles" => [".png", ".jpg", ".jpeg", ".gif", ".bmp"], /* 列出的文件类型 */

    /* 列出指定目录下的文件 */
    "fileManagerActionName" => "listfile", /* 执行文件管理的action名称 */
    "fileManagerListPath" => "/uploads/file/", /* 指定要列出文件的目录 */
    "fileManagerUrlPrefix" => "", /* 文件访问路径前缀 */
    "fileManagerListSize" => 20, /* 每次列出文件数量 */
    "fileManagerAllowFiles" => [
        ".png", ".jpg", ".jpeg", ".gif", ".bmp",
        ".flv", ".swf", ".mkv", ".avi", ".rm", ".rmvb", ".mpeg", ".mpg",
        ".ogg", ".ogv", ".mov", ".wmv", ".mp4", ".webm", ".mp3", ".wav", ".mid",
        ".rar", ".zip", ".tar", ".gz", ".7z", ".bz2", ".cab", ".iso",
        ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".pdf", ".txt", ".md", ".xml"
    ] /* 列出的文件类型 */

]
);