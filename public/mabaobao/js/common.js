(function () {
    document.getElementById('popup').addEventListener('touchstart', function (e) {
        e.stopPropagation();
        e.preventDefault();
        show('pop-up', 'fade');
    }, false);
    document.getElementById('close').addEventListener('touchstart', function () {
        hide('pop-up', 'fade');
    }, false);

    //弹出隐藏层
    function show(show_div,hide_div){
        document.getElementById(show_div).style.display='block';
        document.getElementById(hide_div).style.display='block';
    }

    //关闭弹出层
    function hide(show_div,hide_div){
        document.getElementById(show_div).style.display='none';
        document.getElementById(hide_div).style.display='none';
    }
}());