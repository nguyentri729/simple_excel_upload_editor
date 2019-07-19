<?php
set_time_limit(0);
header("Content-type: application/json; charset=utf-8"); 
require 'vendor/autoload.php';
$result = array(
	'status' => false,
	'data'  => []
);
if (isset($_POST) && !empty($_FILES['file'])) {
    $duoi = explode('.', $_FILES['file']['name']); // tách chuỗi khi gặp dấu .
    $duoi = $duoi[(count($duoi) - 1)]; //lấy ra đuôi file
    // Kiểm tra xem có phải file ảnh không
    if ($duoi === 'xlsx' || $duoi === 'csv') {
        // tiến hành upload
        $file = 'uploads/' . $_FILES['file']['name'];
        if (move_uploaded_file($_FILES['file']['tmp_name'], $file)) {

			if ( $xlsx = SimpleXLSX::parse($file) ) {
				$result = array(
					'status' => true,
					'data'  => $xlsx->rows(),
					'name'	=> $_FILES['file']['name']
				);
			   
			} else {
				$result = array(
					'status' => false,
					'data'  => SimpleXLSX::parseError()
				);
			}
          
        } else { 
				$result = array(
					'status' => false,
					'data'  => 'Không thể upload file'
				);
        }
    } else {
				$result = array(
					'status' => false,
					'data'  => 'Tải lên đúng định dạng file '
				);
    }
    exit(json_encode($result));
}