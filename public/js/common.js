(function () {
    if (document.getElementById('popup1')) {
        document.getElementById('popup1').addEventListener('touchstart', function (e) {
            e.stopPropagation();
            e.preventDefault();
            show('tip1', 'fade1');
        }, false);
        document.getElementById('close1').addEventListener('touchstart', function () {
            hide('tip1', 'fade1');
        }, false);
    }

    if (document.getElementById('popup2')) {
        document.getElementById('popup2').addEventListener('touchstart', function (e) {
            e.stopPropagation();
            e.preventDefault();
            show('tip2', 'fade2');
        }, false);
        document.getElementById('close2').addEventListener('touchstart', function () {
            hide('tip2', 'fade2');
        }, false);
    }



    //弹出隐藏层
    function show(show_div, hide_div) {
        document.getElementById(show_div).style.display = 'block';
        document.getElementById(hide_div).style.display = 'block';
    }

    //关闭弹出层
    function hide(show_div, hide_div) {
        document.getElementById(show_div).style.display = 'none';
        document.getElementById(hide_div).style.display = 'none';
    }
})();