(function(){
    "use strict";
    var oTop = $("#toTop"),
        con = $("#content"),
        isTouch = ("ontouchstart" in document ? "touchstart" : "click");

    var win = new function(){
        var timeout,
            obj = $("#check-win"),
            box = $("#check-win-box"),
            win_tip = $("#check-win-tips"),
            win_text = $("#check-win-text"),
            iframe = $("#iframe");

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
            var text = text || "";
            switch(type){
                case "tips":
                    win_tip.css("display","block");
                    break;
                case "text":
                    win_text.css("display","block");
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
                win.hide();
            } else if(events && events.hasClass('check-win-btn')) {
                win.hide();
                var isiOS = navigator.userAgent.match('iPad') || navigator.userAgent.match('iPhone') || navigator.userAgent.match('iPod'),
                    isAndroid = navigator.userAgent.match('Android'),
                    isDesktop = !isiOS && !isAndroid;

                if(isiOS){
                    var loadDateTime = new Date();
                    window.setTimeout(function() {
                            var timeOutDateTime = new Date();
                            if (timeOutDateTime - loadDateTime < 5000) {
                                window.location.href = "要跳转的页面URL";
                            } else {
                                window.close();
                            }
                        },
                        25);
                    window.location.href = "协议URL";
                } else if (isAndroid) {
                    var state = null;
                    try {
                        state = window.open("协议URL", '_blank');
                    } catch(e) {}
                    if (state) {
                        window.close();
                    } else {
                        window.location.href = "要跳转的页面URL";
                    }
                } else if(isDesktop){
                    return true;
                }

            }
        });

    };

    con.on(isTouch,function(e) {
        var event = $(e.target);
        if(event && event.hasClass('btn')){
            //console.log(e.target);
            win.show("tips");
        }
    });


    /**
     *
     * 加载更多
     * 用户登录
     * 获取验证码
     *
     */
    var nbTitle = $(".nb-title"),
        shopList = $("#stop-list"),
        babyList = $("#baby-list"),
        nearList = $("#near-list"),
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
        i = 1,
        config = {"userInfo": "","code":"","orderForm":"","shopMore":"","babyMore":"","nearMore":""};

    //加载更多
    function moreUrl(a,b) {
        $.post(a,{page:i}, function(data) {
            //console.log(data);
            var str = "";
            $.each(data, function (i, item) {
                str += '<a class="item Fix"><div class="intro"><h4 class="name">' + item.name + '</h4><div class="address">' + item.address + '</div></div><div class="distance">' + item.distance + '</div></a>';
            });
            b.append(str);
            i++;
        },"json");
    }

    nbTitle.on(isTouch,function(e) {
        var event = $(e.target);
        if(event && event.hasClass('more-btn')){
            //event.parent().addClass("on");
            //event.prev().css({"height": "auto","overflow": "hidden"});
            if(event.data("key") == "stop"){
                moreUrl(config.shopMore,shopList);
            }else if($(e.target).data("key") == "baby"){
                moreUrl(config.babyMore,babyList);
            }else if($(e.target).data("key") == "near"){
                moreUrl(config.nearMore,nearList);
            }else{
                return;
            }
        }
        //else if(event && event.hasClass('drop')){
        //    event.parent().next().css({"height": "285px","overflow": "hidden"});
        //    event.parents().removeClass("on");
        //}
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
        }
    }

    getVcodeBtn.on(isTouch,function(){

        if((!loginAble) || (!check_tel())){ return false; }

        var phoneValue = $.trim(phone.val()),
            code_data = {'phone':phoneValue};

        getVcodeBtn.css("pointer-events", "none");
        curCount = count;
        codeAble = false;
        setVCodeBtn();

        //post 请求验证码
        $.post(config.code, code_data, function (data){
            code.focus();
            if(data.status == 1){
                setVCodeBtn();
            }else if (data.status == 0) {
                getVcodeBtn.css("pointer-events", "auto");
            }
        },"json");
    });

    loginBtn.on(isTouch,function(){
        if((!loginAble) || (!check_tel())){ return false; }

        var phoneValue = $.trim(phone.val()),
            codeValue = $.trim(code.val()),
            submit_data = {'phone':phoneValue,'code':codeValue};

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
        $.post(config.userInfo, submit_data, function (data){
            if(data.status == 1){
                //console.log(data);
                //alert(data.message);
                window.location.href = "";
            }else if (data.status == 0) {
                loginBtn.text("提交失败");
                loginAble = true;
            }
        },"json");
    });

    /**
     * 提交订单
     */
    var optionInput = $(".radio"),
        submitBtn = $("#submit-btn");

    optionInput.on(isTouch,function () {
        $(this).addClass("active").siblings(".radio").removeClass("active");
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

    submitBtn.on(isTouch, function(){

        var orderTime = $('input:radio[name="time"]:checked').val(),
            orderAddress = $('input:radio[name="address"]:checked').val(),
            orderTotal = total.text();

        $.post(config.orderForm, {'orderTime':orderTime,'orderAddress':orderAddress,"orderTotal":orderTotal}, function (data) {

        });
    });

    /**
     * 返回顶部
     */
    oTop.on(isTouch,function(){
        con.animate({scrollTop: 0},1000);
        return false;
    });
}());