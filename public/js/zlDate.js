var obj = { date: new Date(), year: -1, month: -1, priceArr: [] };
var htmlObj = { header: "", left: "", right: "" };
var elemId = null;
function getAbsoluteLeft(objectId) {
    var o = document.getElementById(objectId);
    var oLeft = o.offsetLeft;
    while (o.offsetParent != null) {
        oParent = o.offsetParent;
        oLeft += oParent.offsetLeft;
        o = oParent
    }
    return oLeft
}
//获取控件上绝对位置
function getAbsoluteTop(objectId) {
    var o = document.getElementById(objectId);
    var oTop = o.offsetTop + o.offsetHeight + 10;
    while (o.offsetParent != null) {
        oParent = o.offsetParent;
        oTop += oParent.offsetTop;
        o = oParent
    }
    return oTop
}
//获取控件宽度
function getElementWidth(objectId) {
    x = document.getElementById(objectId);
    return x.clientHeight;
}
var pickerEvent = {
    Init: function (elemid) {
        if (obj.year == -1) {
            dateUtil.getCurrent();
        }

        for (var item in pickerHtml) {
            //console.log(pickerHtml);
            pickerHtml[item]();
        }
        var p = document.getElementById("calendar_choose");
        if (p != null) {
            document.body.removeChild(p);
            $('.calendar-matte').remove();
        }
        var html = '<div id="calendar_choose" class="calendar" style="display: block;">';
        html += htmlObj.header;
        html += '<div class="basefix" id="bigCalendar" style="display: block;">';
        html += htmlObj.left;
        html += htmlObj.right;
        html += '<div style="clear: both;"></div>';
        html += "</div></div>";
        $(document.body).append(html);
        $(document.body).append(htmlObj.matte);
        //$(".calendar-matte").on('touchend',function(e){
        //    e.preventDefault();
        //    $(this).hide();
        //    pickerEvent.remove();
        //});

        $('.pkg_circle_bottom').on('tap',function(){
            pickerEvent.getNext();
            var cur_val = $('body').find('.date_text').text();
            var date =cur_val.replace(/[\u4e00-\u9fa5]/g,',');
            var date_str = date.split(',');
            var daycount = DayNumOfMonth(date_str[0],date_str[1]);
            if(cur_val){
                var start_str = cur_val + '01'+' 00:00';
                var end_str = cur_val + daycount+' 00:00';
                var start_time = new Date(start_str.replace(/[\u4e00-\u9fa5]/g,'/')).getTime()/1000 ;
                var end_time = new Date(end_str.replace(/[\u4e00-\u9fa5]/g,'/')).getTime()/1000;
                AjaxTime(start_time,end_time);
            }
        });

        $('.pkg_circle_top').on('tap',function(){
            pickerEvent.getLast();
            var cur_val = $('body').find('.date_text').text();
            var date =cur_val.replace(/[\u4e00-\u9fa5]/g,',');
            var date_str = date.split(',');
            var daycount = DayNumOfMonth(date_str[0],date_str[1]);
            if(cur_val){
                var start_str = cur_val + '01'+' 00:00';
                var end_str = cur_val + daycount+' 00:00';
                var start_time = new Date(start_str.replace(/[\u4e00-\u9fa5]/g,'/')).getTime()/1000 ;
                var end_time = new Date(end_str.replace(/[\u4e00-\u9fa5]/g,'/')).getTime()/1000;
                AjaxTime(start_time,end_time);
            }
        });

        elemId=elemid;var elemObj = document.getElementById(elemid);
        //document.getElementById("picker_last").onclick = pickerEvent.getLast;
        //document.getElementById("picker_next").onclick = pickerEvent.getNext;

        //document.getElementById("picker_today").onclick = pickerEvent.getToday;
        //document.getElementById("calendar_choose").style.left = getAbsoluteLeft(elemid)+"px";
        //document.getElementById("calendar_choose").style.top  = getAbsoluteTop(elemid)+"px";
        //document.getElementById("calendar_choose").style.zIndex = 1000;
        var tds = document.getElementById("calendar_tab").getElementsByTagName("td");
        for (var i = 0; i < tds.length; i++) {
            var s =new Date(show_day()).getTime()/1000;
            var e =new Date(tds[i].getAttribute("date")).getTime()/1000;
            if(tds[i].getAttribute("date") == $("#calendar").val()){
                tds[i].setAttribute("class", "active");
            }
            if (tds[i].getAttribute("date") != null && tds[i].getAttribute("date") != "" && tds[i].getAttribute("price") != "-1" && tds[i].getAttribute("data-num") != "0" && e >= s && tds[i].getAttribute("data-status") != "0") {
                tds[i].onclick = function () {
                    commonUtil.chooseClick(this)
                };
            }
            if(tds[i].getAttribute("date") != null && tds[i].getAttribute("date") != "" && tds[i].getAttribute("price") != "-1" && tds[i].getAttribute("data-num") != "0" && e < s ){
                var reg = new RegExp('(\\s|^)' + 'on' + '(\\s|$)');
                tds[i].className = tds[i].className.replace(reg, ' ');
            }
            if(tds[i].getAttribute("data-status") == "0"){
                var reg = new RegExp('(\\s|^)' + 'on' + '(\\s|$)');
                var reg_today = new RegExp('(\\s|^)' + 'today' + '(\\s|$)');
                var fir = tds[i].firstChild;
                tds[i].className = tds[i].className.replace(reg, ' ');
                tds[i].className = tds[i].className.replace(reg_today, ' ');
                fir.style.color="#cecece"
            }
        }
    },
    getLast: function () {
        dateUtil.getLastDate();
        pickerEvent.Init(elemId);
    },
    getNext: function () {
        dateUtil.getNexDate();
        pickerEvent.Init(elemId);
    },
    getToday:function(){
        dateUtil.getCurrent();
        pickerEvent.Init(elemId);
    },
    getDetail:function(time){
        dateUtil.getDetailDay(time);
        pickerEvent.Init(elemId);
    },
    setPriceArr: function (arr) {
        obj.priceArr = arr;
    },
    remove: function () {
        var p = document.getElementById("calendar_choose");
        if (p != null) {
            document.body.removeChild(p);
        }
    },
    isShow: function () {
        var p = document.getElementById("calendar_choose");
        if (p != null) {
            return true;
        }
        else {
            return false;
        }
    }
};

var pickerHtml = {
    getMatte:function (){
        var matte ='<div class="calendar-matte" style="display: block;position: fixed;top: 0;right: 0;left: 0;bottom: 0;z-index: 100;background: rgba(99,99,99,0.7)">' + '</div>';
        htmlObj.matte = matte;
    },
    getHead: function () {
        var head = '<div class="calendar_left pkg_double_month"><p class="date_text">' + obj.year + '年' + (obj.month<10 ? "0" + obj.month : obj.month) + '月</p><a href="javascript:void(0)" title="上一月" id="picker_last" class="pkg_circle_top"><i></i></a><a href="javascript:void(0)" title="下一月" id="picker_next" class="pkg_circle_bottom "><i></i></a><span class="l-matte"></span><span class="r-matte"></span></div><ul class="calendar_num basefix"><li class="bold">六</li><li>五</li><li>四</li><li>三</li><li>二</li><li>一</li><li class="bold">日</li></ul>';
        htmlObj.header = head;
    },

    getRight: function () {
        var days = dateUtil.getLastDay();
        var week = dateUtil.getWeek();
        var html = '<table id="calendar_tab" class="calendar_right"><tbody>';
        var index = 0;
        for (var i = 1; i <= 42; i++) {
            if (index == 0) {
                html += "<tr>";
            }
            var c = week > 0 ? week : 0;
            if ((i - 1) >= week && (i - c) <= days) {
                var price = commonUtil.getPrice((i - c));
                var order_id = commonUtil.getOrderId(i - c);
                var ticket = commonUtil.getTicket(i-c);
                var intro = commonUtil.getIntro(i-c);
                var status = commonUtil. getStatus(i-c);
                var priceStr = "";
                var idStr = "";
                var ticketStr = "";
                var introStr = "";
                var classStyle = "";
                var statusStr ='';

                if (price != -1) {
                    priceStr = "¥" + price;
                    classStyle = "class='on'";
                    idStr = order_id;
                    ticketStr = ticket;
                    introStr = intro;
                    statusStr = status;
                }

                if (price != -1&& status ==0){
                    priceStr = "";
                }

                if(price != -1&&ticket == 0){
                    priceStr = "售罄";
                    classStyle = "";
                    idStr = order_id;
                    ticketStr = ticket;
                }

                if (price != -1&&obj.year==new Date().getFullYear()&&obj.month==new Date().getMonth()+1&&i-c==new Date().getDate()) {
                    classStyle = "class='on today'";
                }
                //判断今天
                if(obj.year==new Date().getFullYear()&&obj.month==new Date().getMonth()+1&&i-c==new Date().getDate()){
                    html += '<td  ' + classStyle + 'data-id' + '=' + idStr + ' date="' + obj.year + "-"  + (obj.month<10 ? "0" + obj.month : obj.month) + "-" + ((i - c)<10 ? "0" + (i - c) : (i - c)) + '" price="' + price + '" data-num="' + ticketStr +'" data-intro="' + introStr +'" data-status="' + statusStr +'"><a><span class="date basefix" >今天</span><span class="team basefix" style="display: none;">&nbsp;</span><span class="calendar_price01">' + priceStr + '</span></a></td>';
                }
                else{
                    html += '<td  ' + classStyle + 'data-id' + '=' + idStr +' date="' + obj.year + "-"  + (obj.month<10 ? "0" + obj.month : obj.month) + "-" + ((i - c)<10 ? "0" + (i - c) : (i - c)) + '" price="' + price + '" data-num="' + ticketStr +'" data-intro="' + introStr +'" data-status="' + statusStr +'"><a><span class="date basefix">' + (i - c) + '</span><span class="team basefix" style="display: none;">&nbsp;</span><span class="calendar_price01">' + priceStr + '</span></a></td>';
                }
                if (index == 6) {

                    html += '</tr>';
                    index = -1;
                }
            }
            else {
                html += "<td></td>";
                if (index == 6) {
                    html += "</tr>";
                    index = -1;
                }
            }
            index++;
        }
        html += "</tbody></table>";
        htmlObj.right = html;
    }
};
var dateUtil = {
    //根据日期得到星期
    getWeek: function () {
        var d = new Date(obj.year, obj.month - 1, 1);
        return d.getDay();
    },
    //得到一个月的天数
    getLastDay: function () {
        var new_year = obj.year;//取当前的年份        
        var new_month = obj.month;//取下一个月的第一天，方便计算（最后一不固定）        
        var new_date = new Date(new_year, new_month, 1);                //取当年当月中的第一天        
        return (new Date(new_date.getTime() - 1000 * 60 * 60 * 24)).getDate();//获取当月最后一天日期        
    },
    getCurrent: function () {
        var dt = obj.date;
        obj.year = dt.getFullYear();
        obj.month = dt.getMonth() + 1;
        obj.day = dt.getDate();
    },
    getLastDate: function () {
        if (obj.year == -1) {
            var dt = new Date(obj.date);
            obj.year = dt.getFullYear();
            obj.month = dt.getMonth() + 1;
        }
        else {
            var newMonth = obj.month - 1;
            if (newMonth <= 0) {
                obj.year -= 1;
                obj.month = 12;
            }
            else {
                obj.month -= 1;
            }
        }

    },
    getNexDate: function () {
        if (obj.year == -1) {
            var dt = new Date(obj.date);
            obj.year = dt.getFullYear();
            obj.month = dt.getMonth() + 1;
        }
        else {
            var newMonth = obj.month + 1;
            if (newMonth > 12) {
                obj.year += 1;
                obj.month = 1;
            }
            else {
                obj.month += 1;
            }
        }

    },
    getDetailDay: function (time) {
        var date = t_exchange(time);
        var dateStr = date.split('-');
        obj.year = parseInt(dateStr[0]);
        obj.month = parseInt(dateStr[1]);
    }
};
var commonUtil = {
    getPrice: function (day) {
        var dt = obj.year + "-";
        if (obj.month < 10)
        {
            dt += "0"+obj.month;
        }
        else
        {
            dt+=obj.month;
        }
        if (day < 10) {
            dt += "-0" + day;
        }
        else {
            dt += "-" + day;
        }

        for (var i = 0; i < obj.priceArr.length; i++) {
            var s1 = $.trim(t_exchange(obj.priceArr[i].time)) + "";
            var s2 = $.trim(dt) + "";
            if (s1 == s2) {
                return obj.priceArr[i].price;
            }
        }
        return -1;
    },

    getOrderId: function (day) {
        var dt = obj.year + "-";
        if (obj.month < 10)
        {
            dt += "0"+obj.month;
        }
        else
        {
            dt+=obj.month;
        }
        if (day < 10) {
            dt += "-0" + day;
        }
        else {
            dt += "-" + day;
        }

        for (var i = 0; i < obj.priceArr.length; i++) {
            var s1 = $.trim(t_exchange(obj.priceArr[i].time)) + "";
            var s2 = $.trim(dt) + "";
            if (s1 == s2){
                return obj.priceArr[i].order_id;
            }
        }
        return -1;
    },

    getTicket: function (day) {
        var dt = obj.year + "-";
        if (obj.month < 10)
        {
            dt += "0"+obj.month;
        }
        else
        {
            dt+=obj.month;
        }
        if (day < 10) {
            dt += "-0" + day;
        }
        else {
            dt += "-" + day;
        }

        for (var i = 0; i < obj.priceArr.length; i++) {
            var s1 = $.trim(t_exchange(obj.priceArr[i].time)) + "";
            var s2 = $.trim(dt) + "";
            if (s1 == s2){
                var t = obj.priceArr[i].total_num;
                var b =obj.priceArr[i].buy;
                var num = parseInt(t) - parseInt(b);
                return parseInt(num);
            }
        }
        return -1;
    },

    getIntro: function (day) {
        var dt = obj.year + "-";
        if (obj.month < 10)
        {
            dt += "0"+obj.month;
        }
        else
        {
            dt+=obj.month;
        }
        if (day < 10) {
            dt += "-0" + day;
        }
        else {
            dt += "-" + day;
        }

        for (var i = 0; i < obj.priceArr.length; i++) {
            var s1 = $.trim(t_exchange(obj.priceArr[i].time)) + "";
            var s2 = $.trim(dt) + "";
            if (s1 == s2){
                return obj.priceArr[i].back_money;
            }
        }
        return -1;
    },

    getStatus: function (day) {
        var dt = obj.year + "-";
        if (obj.month < 10)
        {
            dt += "0"+obj.month;
        }
        else
        {
            dt+=obj.month;
        }
        if (day < 10) {
            dt += "-0" + day;
        }
        else {
            dt += "-" + day;
        }

        for (var i = 0; i < obj.priceArr.length; i++) {
            var s1 = $.trim(t_exchange(obj.priceArr[i].time)) + "";
            var s2 = $.trim(dt) + "";
            if (s1 == s2){
                return obj.priceArr[i].status;
            }
        }
        return -1;
    },
    chooseClick: function (sender) {
        var date = sender.getAttribute("date");
        var price = sender.getAttribute("price");
        var order_id = sender.getAttribute("data-id");
        var num = sender.getAttribute("data-num");
        var intro = sender.getAttribute("data-intro");
        var el = document.getElementById(elemId);
        if (el != null) {
            sender.firstChild.setAttribute("class", "active");
            $('#calendar').val(date).attr('data-value', date);
            $('.unsubscribe').find('span').text(intro);
            $('#price').text(price);
            $('#order-id').val(order_id);
            $('.intro-all').find('.unsubscribe').remove();
            $('.intro-all').append('<a class="orderIntro unsubscribe">' +'<p>' + '<mark>退订说明：</mark>' + '<span>' + intro + '</span>' + '<i></i>' + '</p>' + '</a>')
            $('.ticket-tip').find('.state_num').remove();
            $('.ticket-tip').append('<span class="state_num">' + ' ，仅剩' + '<span id="ticket_num">' + num + '</span>张</span>');
            setTotal();
            pickerEvent.remove();
            $('.calendar-matte').remove();
        }
    }
};


//计算当前日期
function show_date(){
    var d = new Date();
    var vYear = d.getFullYear();
    var vMon = d.getMonth() + 1;
    var vDay = d.getDate();
    var h = d.getHours();
    var m = d.getMinutes();
    var se = d.getSeconds();
    s=vYear+'-'+vMon+'-'+vDay;
    return s;
}

function show_day(){
    var d = new Date();
    var vYear = d.getFullYear();
    var vMon = d.getMonth() + 1;
    var vDay = d.getDate();
    s=vYear+'-'+(vMon<10 ? "0" + vMon : vMon)+'-'+(vDay<10 ? "0" + vDay : vDay);
    return s;
}

//计算当前月份
function show_month(){
    var d = new Date();
    var vYear = d.getFullYear();
    var vMon = d.getMonth() + 1;
    var vDay = d.getDate();
    var h = d.getHours();
    var m = d.getMinutes();
    var se = d.getSeconds();
    s=vYear+'-'+vMon;
    return s;
}

//标准时间转换
function show_time(){
    var d = new Date();
    var vYear = d.getFullYear();
    var vMon = d.getMonth() + 1;
    var vDay = d.getDate();
    var h = d.getHours();
    var m = d.getMinutes();
    var se = d.getSeconds();
    s=vYear+'-'+(vMon<10 ? "0" + vMon : vMon);
    return s;
}

//取当前时间月份的天数
function getDays(){
    var date = new Date();
    var year = date.getFullYear();
    var mouth = date.getMonth() + 1;
    var days ;
    if(mouth == 2){
        days= year % 4 == 0 ? 29 : 28;
    }
    else if(mouth == 1 || mouth == 3 || mouth == 5 || mouth == 7 || mouth == 8 || mouth == 10 || mouth == 12){
        days= 31;
    }
    else{
        days= 30;

    }
    return days;
}


//取任意月份的天数
function DayNumOfMonth(Year,Month) {
    var d = new Date(Year,Month,0);
    return d.getDate();
}

//时间搓转换成时间
function t_exchange(time){
    var date = new Date(parseInt(time)*1000);
    Y = date.getFullYear() + '-';
    M = (date.getMonth()+1 < 10 ? '0'+(date.getMonth()+1) : date.getMonth()+1) + '-';
    D = (date.getDate() < 10 ? '0'+date.getDate() : date.getDate()) + ' ';
    return Y+M+D;
}

function GetQueryString(name) {
    var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
    var r = window.location.search.substr(1).match(reg);
    if(r!=null)return decodeURI(r[2]); return null;
}

var id = GetQueryString('id'),
    loginTip = $("#tipsDia"),
    tid = GetQueryString('tid');

function tip_show(){
    setTimeout(function(){
        loginTip.text('');
        loginTip.hide();
    },500);
}

var order_time = $('#order_time');
var order_start = order_time.attr('data-start');
var order_end = order_time.attr('data-end');

//请求数据
function AjaxTime(start_time,end_time){
    if((start_time >=order_start && start_time <= order_end) || (end_time >= order_start && end_time <= order_end) || (start_time <= order_start && end_time >= order_end)){

        $.ajax({
            type: 'POST',
            url: '/good/index/calendar',
            dataType: 'json',
            data: {
                'id': id,
                'start_time':start_time,
                'end_time':end_time,
                'order_id':tid
            },
            async: false,
            headers: {
                "VER": 10
            },
            beforeSend:function(){
                loginTip.show();
                loginTip.text("正在疯狂请求...");

            },
            complete:function(){
                tip_show()
            },
            success: function (data) {
                var obj = data.response_params;
                pickerEvent.setPriceArr(obj);
                pickerEvent.Init("calendar");

            },
            error: function (xhr, type) {
                alert('加载出错！！');
            }
        });
    }else if(start_time >= order_end){
        $(".r-matte").show();
        loginTip.show();
        loginTip.text("没有更多数据啦...");
        tip_show();
    }else if(end_time <= order_start){
        $(".l-matte").show();
        loginTip.show();
        loginTip.text("没有更多数据啦...");
        tip_show()
    }
}
