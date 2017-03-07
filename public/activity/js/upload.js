$(function(){
    var viewImg = $("#imglist"),
        imgcount = 0,
        isIOS = navigator.userAgent.match(/iPad/i) || navigator.userAgent.match(/iphone os/i) || navigator.userAgent.match(/ipod/i),
        isAndroid = navigator.userAgent.match(/android/i),
        phoneEle = $('.phone'),
        uploadtipEle = $('#uploadtip'),
        uploadweixin = viewImg.data('uploadweixin');

    if(uploadweixin){
        fileupload();
    } else {
        if(isIOS) {
            fileupload();
        }else if(isAndroid) {
            alert('enter111');
            if (window.getdata) {
                window.getdata.onPickSuccess = function (result, obj) {

                    if(obj) {
                        var status = true;
                        if (obj.height > 1600) {
                            status = false;
                            alert("照片最大高度不超过1600像素");
                        }

                        if (status) {
                            viewImg.append('<li><span class="pic_time"><span class="p_img"></span><em>50%</em></span></li>');
                            viewImg.find("li:last-child").html('<span class="del"></span><img class="wh60" src="data:image/jpeg;base64,'+ result +'"/><input type="hidden" id="file'
                            + imgcount
                            + '" name="fileup[]" value="'
                            + result + '">');

                            $(".del").on("click",function(){
                                $(this).parent('li').remove();
                                $("#upload_image").show();
                            });
                            imgcount++;
                        }
                    }else {
                        var imgStr = result.substring(0, result.indexOf(',{')),
                            jsonStr = result.substring(result.indexOf(',{') + 1, (result.length+1));
                        jsonStr = eval('(' + jsonStr + ')');
                        var status = true;
                        if (jsonStr.height > 1600) {
                            status = false;
                            alert("照片最大高度不超过1600像素");
                        }
                        if (status) {
                            uploadtipEle.hide();
                            viewImg.show().append('<li><span class="pic_time"><span class="p_img"></span><em>50%</em></span></li>');
                            viewImg.find("li:last-child").html('<span class="del"></span><img class="wh60" src="data:image/jpeg;base64,'+ imgStr +'"/><input type="hidden" id="file'
                            + imgcount
                            + '" name="fileup[]" value="'
                            + imgStr + '">');

                            $(".del").on("click",function(){
                                $(this).parent('li').remove();
                                $("#upload_image").show();
                            });
                            imgcount++;
                        }
                    }
                };
                phoneEle.on('click', function () {
                    window.getdata.pickfile();
                });
            }
        }else {
            fileupload();
        }
    }

    function fileupload(){
        var str = '<input type="file" id="upload_image" value="图片上传" accept="image/jpeg,image/gif,image/png" capture="camera">';
        phoneEle.append(str);
        $('#upload_image').localResizeIMG({
            width: 800,
            quality: 0.8,


            success: function (result) {
                var status = true;
                if (result.height > 1600) {
                    status = false;
                    alert("照片最大高度不超过1600像素");
                }
                if (viewImg.find("li").length > 4) {
                    status = false;
                    alert("最多上传5张照片");
                }
                if (status) {
                    uploadtipEle.hide();
                    viewImg.show().append('<li><span class="pic_time"><span class="p_img"></span><em>50%</em></span></li>');
                    viewImg.find("li:last-child").html('<span class="del"></span><img class="uploadimg" src="' + result.base64 + '"/><input type="hidden" id="file'
                    + imgcount
                    + '" name="fileup[]" value="'
                    + result.clearBase64 + '">');

                    $(".del").on("click",function(){
                        $("#imglist").find("li").remove();
                        $("#upload_image").show();
                        $("#uploadtip").show();
                    });
                    imgcount++;
                }
            }
        });
    }
}());