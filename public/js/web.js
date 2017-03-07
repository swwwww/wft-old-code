(function(){
    "use strict";

    var oTop = $("#toTop"),
        con = $("#content"),
        collect = $("#collect"),
        isTouch = ("ontouchend" in document ? "touchend" : "tap"),
        win_tip = $("#check-win-tips");

    var win = new function(){
        var timeout,
            obj = $("#check-win"),
            box = $("#check-win-box"),
            win_tip = $("#check-win-tips"),
            win_text = $("#check-win-text"),
            win_share = $("#check-win-share"),
            text = text || "";

        var defaultFun = function(){
            obj.css("background","rgba(0,0,0,0)");
            box.css({"-webkit-transform":"translate(-50%,-50%) scale(0.4)","transform":"translate(-50%,-50%) scale(0.4)","opacity":"0"});
            timeout = setTimeout(function(){
                obj.css("display","none");
            },400);
        };

        /**
         win.show("tips");//加载提示
         **/
        this.show = function(type,text){
            clearTimeout(timeout);
            win_tip.find("h4").text(text);
            switch(type){
                case "tips":
                    win_tip.css("display","block");
                    break;
                case "text":
                    win_text.css("display","block");
                    break;
                case "share":
                    win_share.css("display","block");
                    break;
            }
            obj.css("display","block");
            timeout = setTimeout(function(){
                obj.css("background","rgba(0,0,0,0.7)");
                box.css({"-webkit-transform":"translate(-50%,-50%) scale(1)","transform":"translate(-50%,-50%) scale(1)","opacity":"1"});
            },100);
        };

        //hide
        this.hide = defaultFun;

        box.on(isTouch,function(event) {
            var events = $(event.target);
            if(events && events.hasClass('check-win-close')) {
                if(events.hasClass('more-info')){
                    win.hide();
                    $(".com-more").prev().css("height","auto");
                    $(".com-more").hide();
                }else{
                    win.hide();
                }
                event.preventDefault();
            } else if(events && events.hasClass('check-win-btn')) {
                var type = events.attr('data');
                var id=events.attr('data-id');
                win.hide();
                var isiOS = navigator.userAgent.match('iPad') || navigator.userAgent.match('iPhone') || navigator.userAgent.match('iPod'),
                    isAndroid = navigator.userAgent.match('Android'),
                    isDesktop = !isiOS && !isAndroid;

                if(isiOS){
                    var loadDateTime = new Date();
                    window.setTimeout(function() {
                            var timeOutDateTime = new Date();
                            if (timeOutDateTime - loadDateTime < 5000) {
                                var ua = window.navigator.userAgent.toLowerCase();
                                window.location.href = "http://a.app.qq.com/o/simple.jsp?pkgname=com.deyi.wanfantian&g_f=991653";
                            } else {
                                window.close();
                            }
                        },
                        25);
                } else if (isAndroid) {
                    var state = null;
                    try {
                        state = window.open("http://a.app.qq.com/o/simple.jsp?pkgname=com.deyi.wanfantian&g_f=991653", '_blank');
                    } catch(e) {}
                    if (state) {
                        window.close();
                    } else {
                        window.location.href = "http://a.app.qq.com/o/simple.jsp?pkgname=com.deyi.wanfantian&g_f=991653i";
                    }
                } else if(isDesktop){
                    return true;
                }
                event.preventDefault();
            }
        });

    };

    con.on(isTouch,"a",function(e) {
        var event = $(e.target);
        if(event && event.hasClass('btn')){
            var moreInfo = event.data('more');
            if(moreInfo == 'more'){
                $('.check-win-close').addClass('more-info');
            }
            win.show("tips");
        } else if(event && event.hasClass('mark')){
            e.preventDefault();
            win_tip.find("h4").text("去app中获取资格");
            win.show("tips");
        } else if(event && event.hasClass('invite-btn')){
            e.preventDefault();
            console.log(event);
            win.show("share");
        }
    });


    /**
     * wheel start
     */
    function wheel(){
        var wheel = $(".sc-wheel"),
            prev = $(".guess-prev"),
            next = $('.guess-next'),
            img = wheel.find('ul'),
            w = img.find('li').outerWidth(true),
            h = img.find('li').height(true),
            len = img.find('li').length,
            startX, startY, moveEndX, moveEndY, X, Y;

        img.css("width", w * len );
        img.css("height",h);

        next.on(isTouch,function(){
            img.stop().animate({'margin-left':-w},500,function()
            {
                img.find('li').eq(0).appendTo(img);
                img.css({'margin-left':0});
            });
        });
        prev.on(isTouch,function(){

            img.stop().animate({'margin-left':w},500,function()
            {
                img.css({'margin-left':0});
                img.find('li:last').prependTo(img);
            });
        });

        wheel.on("touchstart", function(e) {
            startX = e.originalEvent.changedTouches[0].pageX;
            startY = e.originalEvent.changedTouches[0].pageY;
        });
        wheel.on("touchmove", function(e) {
            e.preventDefault();
            moveEndX = e.originalEvent.changedTouches[0].pageX;
            moveEndY = e.originalEvent.changedTouches[0].pageY;
            X = moveEndX - startX;
            Y = moveEndY - startY;

            if ( Math.abs(X) > Math.abs(Y) && X > 0 ) {
                img.stop().animate({'margin-left': w},100,function()
                {
                    img.find('li:last').prependTo(img);
                    img.css({'margin-left':0});
                });

            }
            else if ( Math.abs(X) > Math.abs(Y) && X < 0 ) {
                img.stop().animate({'margin-left': -w},100,function()
                {
                    img.find('li').eq(0).appendTo(img);
                    img.css({'margin-left':0});

                });
            }
        });
    }
    wheel();

    /**
     * wheel end
     */



    /**
     *
     * 加载更多
     * 用户登录
     * 获取验证码
     *
     */
    var nbTitle = $(".nb-title"),
        drop = $(".drop"),
        shopList = $("#stop-list"),
        babyList = $("#baby-list"),
        nearList = $("#near-list"),
        sid = $("#sid").val(),
        getVcodeBtn = $("#captcha"),
        loginTip = $("#loginTip"),
        phone = $("#phone"),
        phoneReg = /^1\d{10}$/,
        code = $("#code"),
        codeAble = true,
        loginAble = true,
        loginBtn = $("#loginBtn"),
        InterValObj,    //timer变量，控制时间
        count = 60,     //间隔函数，1秒执行
        curCount,       //当前剩余秒数
        i = 2,
        wap = $("#wap").val(),
        user_url = $("#ver_url").val(),
        config = {"userInfo": user_url,"code":"/user/login/getcode","orderForm":"/pay/index/index","shopMore":"/web/place/getMore","babyMore":"/web/place/getMore","nearMore":"/web/place/getMore"};


    //加载更多
    function moreUrl(a,b,c) {
        $.post(a,{page:i,'key':c,'id':sid}, function(data) {
            //console.log(data);
            var str = "";

            if(data==''){
                alert('抱歉，没有更多数据了');
                $(".more-btn").attr('data-id',c).hide();
            }

            $.each(data, function (i, item) {
                str += '<a class="item Fix"><div class="intro"><h4 class="name">' + item.shop_name + '</h4><div class="address">' + item.shop_address + '</div></div><div class="distance">' + item.dis + '</div></a>';
            });
            b.append(str);
            i++;
        },"json");
    }

    nbTitle.on("click",function(e) {
        var event = $(e.target);
        if(event && event.hasClass('more-btn')){
            var key = event.data("id");
            if(event.data("key") == "stop"){
                moreUrl(config.shopMore,shopList,key);
            }else if(event.data("key") == "baby"){
                moreUrl(config.babyMore,babyList,key);
            }else if(event.data("key") == "near"){
                moreUrl(config.nearMore,nearList,key);
            }else{
                return;
            }
        }
    });

    drop.click(function(){
        var id = $(this).attr('data-id');
        $("#"+id).slideToggle("show");
    });


    function check_tel () {
        var phoneValue = $.trim(phone.val());
        if(phoneValue == ""){
            loginTip.text("您还未输入手机号");
            phone.focus();
            return false;
        }
        if(!phoneReg.test(phoneValue)){
            loginTip.text("您输入手机号有误");
            return false;
        } else{
            loginTip.text("");
            //console.log(phoneValue);
        }
        return true;
    }

    function setVCodeBtn(){
        getVcodeBtn.text("" + curCount + "s");
        InterValObj = window.setInterval(SetRemainTime, 1000); //启动计时器，1秒执行一次
        getVcodeBtn.addClass('gray-bg');
    }

    function SetRemainTime() {
        curCount--;
        if (curCount == 0) {
            window.clearInterval(InterValObj);//停止计时器
            getVcodeBtn.css("pointer-events","auto");
            getVcodeBtn.text("重新获取");
            getVcodeBtn.removeClass('gray-bg');
        }
        else {
            getVcodeBtn.text("" + curCount+"s" );
            getVcodeBtn.attr({"disabled":"disabled"});
        }
    }

    getVcodeBtn.on(isTouch,function(){
        if((!loginAble) || (!check_tel())){ return false; }

        var phoneValue = $.trim(phone.val()),
            is_wap = $("#wap").val(),
            code_data = {'phone':phoneValue,'wap':is_wap};

        getVcodeBtn.css("pointer-events", "none");
        curCount = count;
        codeAble = false;

        //post 请求验证码

        $.ajax({
            type: "POST",
            url: "/user/login/getcode",
            //        dataType:"json",
            async: true,
            data:code_data,
            headers: {
                "VER": 10
            },
            success: function (result) {
                if (result.response_params.status == 0) {
                    alert(result.response_params.message);
                    getVcodeBtn.css("pointer-events", "auto");
                } else {
                    setVCodeBtn();
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                //            alert("XHR=" + XMLHttpRequest + "\ntextStatus=" + textStatus + "\nerrorThrown=" + errorThrown);
                if (XMLHttpRequest.status == 401) {
                    //alert('授权失败');
                    // 跳转授权页面
                    //alert(XMLHttpRequest.responseJSON.message);
                    window.location.href = '<?php echo $authorUrl;?>';

                }
                else if (XMLHttpRequest.status == 403) {
                    alert('接口验证失败，非法访问');
                }
                else if (XMLHttpRequest.status == 400) {
                    alert('请求参数错误');
                }
                else {
                    alert('接口异常，返回状态码：' + XMLHttpRequest.status)
                }
            }

        });
    });

    loginBtn.on(isTouch,function(){
        if((!loginAble) || (!check_tel())){ return false; }

        var phoneValue = $.trim(phone.val()),
            codeValue = $.trim(code.val()),
            uid = loginBtn.attr('data-uid'),
            tourl = $("#tourl").val(),

            submit_data = {'phone':phoneValue,'code':codeValue,'uid':uid,'wap':wap,'is_weixin': 1},
            backurl = $("#backurl").val();


        if(codeValue == ""){
            loginTip.text("您还未输入验证码");
            code.focus();
            return false;
        } else {
            getVcodeBtn.text("获取");
            getVcodeBtn.addClass('gray-bg');
            window.clearInterval(InterValObj); //停止计时器
        }

        loginBtn.text("提交中...");
        loginAble = false;
        //post

        var url ;
        var current_city = localStorage.getItem("select_city"),
            wei_city = $("#city").val();
        if(!current_city){
            localStorage.setItem("select_city",wei_city);
        }
        var city = current_city;
        var type=$("#type").val();
        if(type==1){
            url = "/user/account/verifycode";
        }else{
            url="/user/login/bindphone";
        }
        $.ajax({
            type: "POST",
            url: url,
            //        dataType:"json",
            async: true,
            data:submit_data,
            headers: {
                "VER": 10,
                "CITY":encodeURI(city)
            },
            success: function (result) {
                if (result.response_params.status == 0) {
                    alert(result.response_params.message);
                    loginAble = true;
                } else {
                    // 成功
                    alert(result.response_params.message);
                    window.location.href = tourl+"?tourl="+encodeURIComponent(backurl);
                    return false;
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                //            alert("XHR=" + XMLHttpRequest + "\ntextStatus=" + textStatus + "\nerrorThrown=" + errorThrown);
                if (XMLHttpRequest.status == 401) {
                    //alert('授权失败');
                    // 跳转授权页面
                    //alert(XMLHttpRequest.responseJSON.message);
                    window.location.href = '<?php echo $authorUrl;?>';

                }
                else if (XMLHttpRequest.status == 403) {
                    alert('接口验证失败，非法访问');
                }
                else if (XMLHttpRequest.status == 400) {
                    alert('请求参数错误');
                }
                else {
                    alert('接口异常，返回状态码：' + XMLHttpRequest.status)
                }
            }

        });

    });

    /**
     * 提交订单
     */
    var optionInput = $(".radio"),
        submitBtn = $("#submit-btn");

    optionInput.on(isTouch,function () {
        $(this).addClass("active").siblings(".radio").removeClass("active");
        var id = $(this).find("input").val();
        var t = $(this).attr('data-time');
        $(".a_"+id).addClass('active').siblings().removeClass("active");
        $(".t_"+t).eq(0).addClass('active').siblings().removeClass("active");
        $(".a_"+id).attr('checked',true);
        $(".t_"+id).attr('checked',true);
        $("#order_id").val(id);
        return $(this).data("value");
    });


    //获得文本框对象
    var t = $("#text-box"),
        add = $("#add"),
        min = $("#min"),
        total = $("#total"),
        price = parseFloat($("#price").text()).toFixed(2);

    //数量增加操作
    add.on(isTouch,function(){
        min.removeClass("disabled");
        var n= $(this).prev().text();
        var num= parseInt(n)+1;
        var limit_num = $("#limit_num").val(),
            surplus_num = $("#surplus_num").val(),
            g_limit = $("#g_limit").val(),
            g_buy = $("#g_buy").val();
        if(g_buy>0){
            if(num>g_limit){
                return false;
            }
        }
        if(num>limit_num || num>surplus_num){
            return false;
        }
        //if(num==0){ return;}
        $(this).prev().text(num);
        setTotal();
    });
    //数量减少操作
    min.on(isTouch,function(){
        var n=$(this).next().text();
        var num=parseInt(n)-1;
        if(num==0){ min.addClass("disabled");return;}
        $(this).next().text(num);
        setTotal();
    });
    //计算操作
    function setTotal(){
        total.html((parseInt(t.text())*price).toFixed(2));
    }
    //初始化
    setTotal();

    submitBtn.on(isTouch, function(e){
        e.preventDefault();
        var orderTime = $('input:radio[name="tid"]:checked').val(),
            orderAddress = $('input:radio[name="address"]:checked').val(),
            orderNum = t.text(),
            order_id=$("#order_id").val(),
            uid = $('input[name="uid"]').val(),
            group_buy = $('input[name="group_buy"]').val(),
            group_buy_id = $('input[name="group_buy_id"]').val(),
            coupon_id = $('input[name="coupon_id"]').val(),
            name = $('input[name="name"]').val(),
            use_account_money = $('input[name="use_account_money"]').val(),
            phone = $('input[name="phone"]').val(),
            is_wap= $("#is_wap").val(),
            is_remark = $("#remark").val(),
            user_remark = $("#user_remark").val(),
            submitDate = {"uid":uid,"coupon_id":coupon_id,"number":orderNum,"name":name,'phone':phone,'order_id':order_id,'order_type':2,'group_buy':group_buy,'group_buy_id':group_buy_id,'message':user_remark
            };

        //if(orderTime == null || orderAddress == null){
        //    win.show("text");
        //    return false;
        //}


        if(order_id==null){
            win.show("text");
            return false;
        }

        if(is_remark==1){
            if(!user_remark){
                alert('请填写备注！');
                return false;
            }
        }

        if(orderAddress==orderTime){
            order_id =orderTime;}

        $('#submit-btn').val('请稍候..').css({'background-color': '#ccc'}).attr({"disabled": "disabled"});
        //window.location.href="/web/wappay/payment?coupon_id="+coupon_id+"&number="+orderNum+"&name="+name+"&phone="+phone+"&order_id="+order_id+"&order_type=2&group_buy="+group_buy+"&group_buy_id="+group_buy_id;


        var city = localStorage.getItem("select_city");

        window.location.href="/web/wappay/payment?coupon_id="+coupon_id+"&number="+orderNum+"&name="+name+"&phone="+phone+"&order_id="+order_id+"&group_buy="+group_buy+"&group_buy_id="+group_buy_id+"&message="+user_remark+"&city="+encodeURI(city);
//        $.ajax({
//            type: "POST",
//            url: "/pay/index/index",
//            //        dataType:"json",
//            async: true,
//            data: submitDate,
//            headers: {
//                "VER": 10,
//                "CITY":encodeURI(city)
//            },
//            success: function (result) {
//                if (result.response_params.status == 0) {
//                    alert(result.response_params.message);
//                    window.location.reload();
//                } else if(result.response_params.status == -1){
//                    alert(result.response_params.message);
//                    window.location.reload();
//
//                }else{
//                    //todo 成功
//                    window.location.href = '/web/wappay/payment?showwxpaytitle=1&orderId=' + result.response_params.order_sn+"&group_buy_id="+ result.response_params.group_buy_id;
//                }
//            },
//            error: function (XMLHttpRequest, textStatus, errorThrown) {
//                //            alert("XHR=" + XMLHttpRequest + "\ntextStatus=" + textStatus + "\nerrorThrown=" + errorThrown);
//                if (XMLHttpRequest.status == 401) {
//                    //todo 跳转授权页面
//                    //alert(XMLHttpRequest.responseJSON.message);
//                    window.location.href = '<?php echo $authorUrl;?>';
//                }
//                else if (XMLHttpRequest.status == 403) {
//                    alert('接口验证失败，非法访问');
//                }
//                else if (XMLHttpRequest.status == 400) {
////                        window.location.href = '<?php //echo $authorUrl;?>//';
//                    alert('请求参数错误');
//                }
//                else {
//                    alert('接口异常，返回状态码：' + XMLHttpRequest.status)
//                }
//
//            }
//        });
    });

    collect.on(isTouch,function(){
        var id = $(this).attr('data');
        var uid = $("#uid").val();
        var act = '';
        if($(this).hasClass("on")){
            $(this).removeClass("on");
            $(this).text("收藏");
            act='del';
        } else {
            $(this).addClass("on");
            $(this).text("已收藏");
            act='add';
        }
        if(uid==0){
            var ask=confirm('请先登录');
            if(ask==true){
                window.location.href="/web/wappay/register?tourl="+decodeURI('/web/place/index?id='+id);
            }
        }
        $.ajax({
            type: "POST",
            url: "/web/place/collect",
            //        dataType:"json",
            async: true,
            data: {"uid":uid,"link_id":id,'type':'shop','act':act
            },

            success: function (result) {
                if (result.response_params.status == 0) {
                    alert(result.response_params.message);
                }else{
                    alert(result.response_params.message);
                }

            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                //            alert("XHR=" + XMLHttpRequest + "\ntextStatus=" + textStatus + "\nerrorThrown=" + errorThrown);
                if (XMLHttpRequest.status == 401) {
                    //todo 跳转授权页面
                    //alert(XMLHttpRequest.responseJSON.message);
                    window.location.href = '<?php echo $authorUrl;?>';
                }
                else if (XMLHttpRequest.status == 403) {
                    alert('接口验证失败，非法访问');
                }
                else if (XMLHttpRequest.status == 400) {
//                        window.location.href = '<?php //echo $authorUrl;?>//';
                    alert('请求参数错误');
                }
                else {
                    alert('接口异常，返回状态码：' + XMLHttpRequest.status)
                }

            }
        });
    });

    $("#method").on(isTouch,function(e){
        var shopsFocus = $("#shops-focus");
        console.log(shopsFocus[0]);
        $('body').animate({scrollTop: shopsFocus[0].offsetTop-80}, 1000);
        $('body').append('<div id="overlay"></div>');
        var overlay = $('#overlay');
        overlay.css({
            'position': 'fixed',
            'top': 0,
            'left': 0,
            'right': 0,
            'bottom': 0,
            'background': 'rgba(0,0,0,.7)',
            'width': '100%',
            'height': '100%',
            'z-index': 99 //保证这个悬浮层位于其它内容之上
        });
        var shopsClone = shopsFocus.clone();
        shopsClone.addClass("sp-pos");
        overlay.html(shopsClone);
        setTimeout(function(){
            overlay.remove();
            shopsFocus.clone().remove();
        }, 5000); //设置5秒后覆盖层自动淡出
    });

    $("#overlay").on(isTouch,function(){
        $(this).hide();
    });

    /**
     *
     * 分类列表
     *
     */
    var select_menu = $("#select_menu");
    select_menu.on(isTouch,function(e){
        var event = $(e.target);
        event.next().show();
        event.parent().siblings().find('.tag_menu').hide();
    });

    /**
     * 固定category
     * 返回顶部
     */
    var category = $('.category'),
        ref_min = $("#sign")[0];
    oTop.hide();
    $(window).scroll(function() {
        var scrollTop = $(window).scrollTop();
        if(scrollTop > 100){
            oTop.fadeIn(500);
        } else {
            oTop.fadeOut(500);
        }
        if(ref_min){
            var ref_height_min = ref_min.offsetTop + category[0].offsetHeight;
            if (ref_height_min < scrollTop){
                category.addClass('fixed');
            }
            else{
                category.removeClass('fixed');
            }
        } else {
            return false;
        }
    });
    oTop.on(isTouch,function(){
        $('body').animate({ scrollTop: 0 }, 1000);
        return false;
    });

    //获取url参数值
    function GetQueryString(name)
    {
        var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
        var r = window.location.search.substr(1).match(reg);
        if(r!=null)return decodeURI(r[2]); return null;
    }


    //获取cookie值
    function getCookie(name)
    {
        var arr,reg=new RegExp("(^| )"+name+"=([^;]*)(;|$)");
        if(arr=document.cookie.match(reg))
            return decodeURI(arr[2]);
        else
            return null;
    }


    //封装ajax
    jQuery.more=function(url, data,headers,async, type, dataType, successfn, errorfn) {
        async = (async==null || async=="" || typeof(async)=="undefined")? "true" : async;
        headers = (headers==null || headers=="" || typeof(headers)=="undefined")? {"VER": 10} : headers;
        type = (type==null || type=="" || typeof(type)=="undefined")? "post" : type;
        dataType = (dataType==null || dataType=="" || typeof(dataType)=="undefined")? "json" : dataType;
        data = (data==null || data=="" || typeof(data)=="undefined")? {"date": new Date().getTime()} : data;
        $.ajax({
            type: type,
            async: async,
            data: data,
            headers:headers,
            url: url,
            dataType: dataType,
            success: function(d){
                successfn(d);
            },
            error: function(e){
                errorfn(e);
            }
        });
    };
}());

