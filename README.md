"玩翻天"项目API接口与WEB端
=========================

本项目具体包含"玩翻天"项目API相关接口,WEB展示页面与商家后台.

# 接口文档 #


[接口文档存放于doc目录](doc/docs/index.md)，html模板由mkdocs生成

# 部署说明 #

本项目所有文件编码都为UTF-8,包括数据库
    
sql文件位于SQL文件夹下
    
数据库配置文件默认位于 V1\config

Swoole存放异步处理模块，如发送短信，推送等等
  
# 环境要求 #

1.Centos 版本无特别要求
2.Mysql5.5或以上
3.PHP5.5
4.nginx 1.7.4


# 脚本说明 #

AutoBackOrder.php 脚本负责订单过期或快过期时的自动退订与提醒，所以使用到了第三方短信接口

AutoRecovery.php  脚本为未付款订单自动回收脚本
    
# Nginx配置参考#

    server {
            listen 90;
            server_name _;
            index index.php index.html;
            root /work/web/bxbw.deyi.com/public;
    
        location / {
                     try_files $uri @wft;
             }
             location @wft {
                     rewrite ^.*$ /index.php last;
             }
      
            location ~* /uploads/.*\.(jpg|gif|png) {
                 set $x -;
                 set $y -;
                 if ($arg_x) {
                        set $x $arg_x;
                 }
                 if ($arg_y) {
                        set $y $arg_y;
                 }
                 image_filter resize $x $y;
            }
    
    
            include php.conf;
    }
    
本项目基于zend framework2,由于使用到路由功能,nginx配置与常规站点有点不同,如果疑问,请与我联系

联系方式 wwjie@vip.deyi.com

