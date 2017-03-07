(function(){
    //保险单弹窗
    "use strict";
    var isTouch = ("ontouchend" in document ? "touchend" : "tap");

    $("#policy-btn").on("touchend",function(e){
        e.preventDefault();
        $(".matte").show();
        $(".policy-popup").show();
    });
    $(".close-btn").on("touchend",function(e){
        e.preventDefault();
        $(".matte").hide();
        $(".policy-popup").hide();
    });

    //var t_menu = $(".t_menu");
    //var len = t_menu.length;
    //for(var i=0;i<len;i++){
    //    if($(t_menu[i]).attr("data-time")==$(t_menu[i+1]).attr("data-time")){
    //        $(t_menu[i+1]).hide();
    //    }
    //}
    //
    //var a_menu = $(".a_menu");
    //var length = t_menu.length;
    //for(var i=0;i<length;i++){
    //    if($(a_menu[i]).attr("data-addr")==$(a_menu[i+1]).attr("data-addr")){
    //        $(a_menu[i+1]).hide();
    //    }
    //}

    var optionInput = $(".radio"),
        submitBtn = $("#submit-btn");

    //optionInput.on(isTouch,function () {
    //    $(this).addClass("active").siblings(".radio").removeClass("active");
    //    var people_num = $(this).attr("data-people");
    //    var id = $(this).find("input").val();
    //    var t = $(this).attr('data-time');
    //    $(".a_"+id).addClass('active').siblings().removeClass("active");
    //    $(".t_"+t).eq(0).addClass('active').siblings().removeClass("active");
    //    $(".a_"+id).attr('checked',true);
    //    $(".t_"+id).attr('checked',true);
    //    $("#order_id").val(id);
    //    $("#insure_num_per_order").val(people_num);
    //    return $(this).data("value");
    //});

    //获得文本框对象
    var t = $("#text-box"),
        add = $("#add"),
        min = $("#min"),
        total = $("#total"),
        tip = $("#tipsDia"),
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

    //radio单选框取消选中
    $(".policy-radio").on("tap",function(){
        $("#policy-icon").attr("checked",false);
    });

    //选择时间地点表单提交

    submitBtn.on(isTouch, function(e){
        e.preventDefault();
        var orderTime = $('input:radio[name="tid"]:checked').val(),
            orderAddress = $('input:radio[name="address"]:checked').val(),
            orderNum = t.text(),
            order_id=$("#order-id").val(),
            uid = $('input[name="uid"]').val(),
            group_buy = $('input[name="group_buy"]').val(),
            group_buy_id = $('input[name="group_buy_id"]').val(),
            coupon_id = $('input[name="coupon_id"]').val(),
            name = $('input[name="name"]').val(),
            phone = $('input[name="phone"]').val(),
            is_wap= $("#is_wap").val(),
            link_address= $(".address-position").val(),
            is_remark = $("#remark").val(),
            user_remark = $("#remark-info").val(),
            has_addr = $("#has_addr").val(),
            insure_num_per_order = $("#insure_num_per_order").val(),
            associates_ids = $("#associates_ids").val(),
            submitDate = {"uid":uid,"coupon_id":coupon_id,"number":orderNum,"name":name,'phone':phone,'order_id':order_id,'order_type':2,'group_buy':group_buy,'group_buy_id':group_buy_id,'message':user_remark
            };


        if(order_id==null){
            tip.text("请选择时间地点");
            tip.show();
            setTimeout(function(){
                tip.hide();
                submitBtn.attr('disabled', false);
            },3000);
            return false;
        }
        if(has_addr==1){
            if(!link_address){
                tip.text("请填写收货地址！");
                tip.show();
                setTimeout(function(){
                    tip.hide();
                    submitBtn.attr('disabled', false);
                },3000);
                return false;
            }
        }
        if(is_remark==1){
            if(!user_remark){
                tip.text("请填写备注！");
                tip.show();
                setTimeout(function(){
                    tip.hide();
                    submitBtn.attr('disabled', false);
                },3000);
                return false;
            }
        }

        if(orderAddress==orderTime){
            order_id =orderTime;}

        $('#submit-btn').val('请稍候..').css({'background-color': '#ccc'}).attr({"disabled": "disabled"});

        var city = localStorage.getItem("select_city");

        window.location.href="/web/wappay/payment?coupon_id="+coupon_id+"&number="+orderNum+"&name="+name+"&phone="+phone+"&order_id="+order_id+"&group_buy="+group_buy+"&group_buy_id="+group_buy_id+"&message="+user_remark+"&city="+encodeURI(city)+"&address="+link_address+"&associates_ids="+associates_ids;
    });
})();
