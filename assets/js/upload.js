  //xử lý khi có sự kiện click
    $('#upload').on('click', function () {
       $("#upload").attr("disabled", true).html('Loading...');

        $('#my').html('');
         $('#download').hide();
        //Lấy ra files
        var file_data = $('#file').prop('files')[0];
       
       
        var match = ["application/octet-stream", "image/png", "image/jpg",];
        //kiểm tra kiểu file
        if (true) {
            //khởi tạo đối tượng form data
            var form_data = new FormData();
            //thêm files vào trong form data
            form_data.append('file', file_data);
            //sử dụng ajax post
            $.ajax({
                url: 'upload.php', // gửi đến file upload.php 
                dataType: 'text',
                cache: false,
                contentType: false,
                processData: false,
                data: form_data,
                type: 'post',
                success: function (respon) {
                    res = JSON.parse(respon);

                    if(res['status']){
                        $('#download').show();

                         var row = res['data'].length;
                         var width_screen = $( window ).width();

                         //kich co man hinh
                         size = Math.round((width_screen) / row);
                         var width = [];
                         for (var i = 0; i < row; i++) {
                            width[i] = size;
                         }
                       
                         $('#my').jexcel({ data:res['data'], colWidths: width});
                         $('#file_name').val(res['name']);
                         //$('#my').jexcel('setWidth', 1, size);

                          $('.status').html('success').hide();
                    }else{

                         $('.status').html(res['data']).show();
                    }
                     $("#upload").attr("disabled", false).html('Upload');
                    $('#file').val('');
                }
            });
        } else {

            $('.status').html('Upload file sai định dạng').show();
            $('#file').val('');
        }
        return false;
    });