(function () {
    'use strict';
    var isTouch = ("ontouchstart" in document ? "touchstart" : "click"),
        token = '654321',
        config = {
            "userInfo": "/tianshi/index/query",
            "giftForm": "/tianshi/index/use",//贝乐园商家点击确认
            "code": "/tianshi/index/use",//武商代金券
            "mailForm": "/tianshi/index/use",//领取邮寄奖品
            "ems": "/tianshi/index/mail",//单号
            "person": "/tianshi/index/use",//积分&现金券
            "look": "/tianshi/index/look"//查看现金券
        };
    var win = new function () {
        var timeout,
            obj = $("#check-win"),
            box = $("#check-win-box"),
            list = $(".check-win-list"),
            win_up = $("#check-win-up"),
            win_send = $("#check-win-send"),
            win_code = $("#check-win-code"),
            win_mail = $("#check-win-mail"),
            win_tips = $("#check-win-tips"),
            win_ems = $("#check-win-ems"),
            win_code2 = $("#check-win-code2"),
            noaward = $("#check-win-noaward"),
            waiting = $("#check-win-waiting");

        var defaultFun = function () {
            obj.css("background", "rgba(0,0,0,0)");
            box.css({
                "-webkit-transform": "translate(-50%,-50%) scale(0.4)",
                "transform": "translate(-50%,-50%) scale(0.4)",
                "opacity": "0"
            });
            timeout = setTimeout(function () {
                obj.css("display", "none");
            }, 400);
        };

        /**
         win.show("tips");//加载提示
         **/
        this.show = function (type, text) {
            clearTimeout(timeout);
            var text = text || "";
            list.css("display", "none");
            switch (type) {
                case "up":
                    win_up.css("display", "block");
                    break;
                case "send":
                    win_send.css("display", "block");
                    break;
                case "code":
                    win_code.css("display", "block");
                    break;
                case "mail":
                    win_mail.css("display", "block");
                    break;
                case "tips":
                    win_tips.css("display", "block");
                    break;
                case "ems":
                    win_ems.css("display", "block");
                    break;
                case "code2":
                    win_code2.css("display", "block");
                    break;
                case "noaward":
                    noaward.css("display", "block");
                    break;
                case "waiting":
                    waiting.css("display", "block");
                    break;
            }
            obj.css("display", "block");
            timeout = setTimeout(function () {
                obj.css("background", "rgba(0,0,0,0.7)");
                box.css({
                    "-webkit-transform": "translate(-50%,-50%) scale(1)",
                    "transform": "translate(-50%,-50%) scale(1)",
                    "opacity": "1"
                });
            }, 100);
        };

        //hide
        this.hide = defaultFun;
        box.on(isTouch, function (event) {
            var events = $(event.target);
            if (events && events.hasClass('gogo')) {
                window.location.reload();
            } else if (events && events.hasClass('close')) {
                window.location.reload();
            } else if (events && events.hasClass('cancel')) {
                window.location.reload();
            } else if (events && events.hasClass('check-win-close')) {
                win.hide();
                event.preventDefault();
            } else if (events && events.hasClass('check-win-btn')) {
                win.hide();
                event.preventDefault();
            } else if (events && events.hasClass('confirm')) {
                var dataInfo = events.data('info');
                if (dataInfo == "gift") {
                    //贝乐园体验券
                    var awardid = events.data('awardid');//奖品id
                    var code = document.getElementById(awardid + "code").value;//验证码
                    if (code != 1234) {
                        nowordtext.text("验证码错误")
                        win.show("noaward");
                    }
                    win.show("waiting");
                    $.post(config.giftForm, {
                        "type": 3,
                        "awardid": events.data('awardid'),
                        "token": token,
                        "code": code,
                        "async": false
                    }, function (data) {
                        if (data.c == 1) {
                            window.location.href = "/tianshi/index/result?token=" + token + "&t=" + (new Date()).valueOf();
                        } else if (data.c == 0) {
                            nowordtext.text(data.m)
                            win.show("noaward");
                        }
                    }, "json");
                } else if (dataInfo == "mail") {
                    //邮寄表单的提交
                    if ((!loginAble) || (!check_name()) || (!check_tel()) || (!check_msg())) {
                        return false;
                    }
                    var mail_data = $("#mail-form").serialize();
                    mail_data = decodeURIComponent(mail_data, true);
                    $.post(config.mailForm, mail_data, function (data) {
                        if (data.c == 1) {
                            win.hide();
                            timeout = setTimeout(function () {
                                win.show("tips");
                            }, 400);
                        } else if (data.c == 0) {
                            nowordtext.text(data.m)
                            win.show("noaward");
                        }
                    }, "json");
                }
            }
        });
    };

    var loginTip = $("#loginTip"),
        loginName = $("#loginName"),
        loginAdr = $("#loginAdr"),
        name = $("#name"),
        nameReg = /^[\u4E00-\u9FA5]+$/,
        phone = $("#phone"),
        phoneReg = /^1\d{10}$/,
        address = $("#address"),
        submitBtn = $("#submitBtn"),
        loginAble = true;

    function check_name() {
        var nameValue = $.trim(name.val());
        if (nameValue == "") {
            loginName.text("*您还未输入姓名");
            name.focus();
            return false;
        }
        if (!nameReg.test(nameValue)) {
            loginName.text("*您输入中文名字");
            return false;
        } else {
            loginName.text("");
        }
        return true;
    }

    function check_msg() {
        var addressValue = $.trim(address.val());
        if (addressValue == "") {
            loginAdr.text("*您还未输入收件地址");
            address.focus();
            return false;
        } else {
            loginAdr.text("");
        }
        return true;
    }


    function check_tel() {
        var phoneValue = $.trim(phone.val());
        if (phoneValue == "") {
            loginTip.text("*您还未输入手机号");
            phone.focus();
            return false;
        }
        if (!phoneReg.test(phoneValue)) {
            loginTip.text("*您输入手机号有误");
            return false;
        } else {
            loginTip.text("");
        }
        return true;
    }

    submitBtn.on(isTouch, function () {

        if ((!check_tel())) {
            return false;
        }

        var phoneValue = $.trim(phone.val()),
            submit_data = {'phone': phoneValue, 'token': token};

        submitBtn.text("提交中...");
        console.log(123);
        $.post(config.userInfo, submit_data, function (data) {
            console.log(data);
            if (data.c == 0) {
                loginTip.text("*" + data.m);
                submitBtn.text("查询");
                return false;
            } else {
                //跳转地址
                window.location.href = "/tianshi/index/result?token=" + token + "&t=" + (new Date()).valueOf();
            }
        }, "json");
    });

    var giftForm = $(".gift-form"),
        ua = window.navigator.userAgent.toLowerCase(),
        confirm = $("#send-ok"),
        codeMark = $("#code-mark"),
        codePsd = $("#code-psd"),
        codeMark1 = $("#code-mark1"),
        codePsd1 = $("#code-psd1"),
        codeMark2 = $("#code-mark2"),
        codePsd2 = $("#code-psd2"),
        emsNum = $("#ems-num"),
        nowordtext = $("#nowordtext")

    giftForm.on(isTouch, function (e) {
        var event = $(e.target);
        if (event && event.hasClass('button') || event.hasClass('check-code')) {
            //判断用户的APP是不是最新版本
            //if (true) {
            if (true) {
                var moreInfo = event.data('user');
                var awardid = event.data('awardid');//奖品id

                if (moreInfo == 'company') {
                    //商家体验券的领取按钮
                    $("#send-ok").attr("data-awardid", awardid);
                    win.show("send");
                } else if (moreInfo == 'person') {
                    //积分&现金券
                    $.post(config.person, {
                        'type': 2,
                        'token': token,
                        'awardid': awardid,
                        "async": false
                    }, function (data) {
                        if (data.c == 1) {
                            window.location.reload();
                        } else if (data.c == 0) {
                            nowordtext.text(data.m)
                            win.show("noaward");
                        }
                    }, 'json');
                } else if (moreInfo == 'code') {
                    //武商网
                    var number = event.data('number');
                    $.post(config.code, {
                        'type': 4,
                        'token': token,
                        'number': number,
                        'awardid': awardid,
                        "async": false
                    }, function (data) {
                        if (data.c == 1) {
                            if (data.o.length > 1) {
                                codeMark1.text(data.o[0].code);
                                codePsd1.text(data.o[0].password);
                                codeMark2.text(data.o[1].code);
                                codePsd2.text(data.o[1].password);
                                win.show("code2");
                            } else {
                                codeMark.text(data.o.code);
                                codePsd.text(data.o.password);
                                win.show("code");
                            }
                        } else if (data.c == 0) {
                            nowordtext.text(data.m)
                            win.show("noaward");
                        }
                    }, 'json');
                } else if (moreInfo == "mail") {
                    //邮局的领奖
                    win.show("mail");
                } else if (moreInfo == "ems") {
                    //快递单号
                    $.post(config.ems, {'token': token}, function (data) {
                        if (data.c == 1) {
                            emsNum.text(data.o.number);
                            win.show("ems");
                        }
                    }, 'json');
                    win.show("ems");
                } else if (moreInfo == "look") {
                    var number = event.data('number');
                    $.post(config.look, {'number': number, 'token': token}, function (data) {
                        if (data.c == 1) {
                            if (data.o.length > 1) {
                                codeMark1.text(data.o[0].code);
                                codePsd1.text(data.o[0].password);
                                codeMark2.text(data.o[1].code);
                                codePsd2.text(data.o[1].password);
                                win.show("code2");

                            } else {
                                codeMark.text(data.o.code);
                                codePsd.text(data.o.password);
                                win.show("code");
                            }
                        }
                    }, 'json');
                }
            } else {
                win.show("up");
            }
        }
    });
}());