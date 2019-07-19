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
                    
                    console.log(respon);
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