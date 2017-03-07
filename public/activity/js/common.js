(function(){

    $('.prize').on('tap','a',function(e){
        var target = $(e.target).closest('a');
        $(this).children('.fudai').show();
        $(this).css('background', 'none');

        setTimeout(function(){
            $.post("/activity/huiju/takeprize",function(data){
                if(data.status == 1){
                    target.find('p').show().empty().append(data.message);
                }
                else{
                    target.find('p').show().empty().append(data.message);
                }
            });
        },1000);
    });

  /*  $("#invite-btn").on("tap",function(){
        $(".matte").show();
    });
    $(".matte").on("tap",function(){
        $(".matte").hide();
    });


    $(".btn1").on("tap",function(){
        $(".popup").show();
        $(".black").show();
    });

    $(".cross").on("tap",function(){
        $(".popup").hide();
        $(".black").hide();
    });
    $(".popup-btn").on("tap",function(){
        $(".popup").hide();
        $(".black").hide();
    });*/
})();