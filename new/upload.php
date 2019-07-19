<?php
set_time_limit(0);
function create_html_table($for){
		$table = '<table border="1" cellpadding="3" style="border-collapse: collapse" id="myTable">';
		foreach( $for as $r ) {

			$table .= '<tr><td class="editMe">'.implode('</td><td class="editMe">', $r ).'</td></tr>';

		}
		$table .= '</table>';
		return $table;
}
set_time_limit(0);
header("Content-type: application/json; charset=utf-8"); 
require '../vendor/autoload.php';
$result = array(
	'status' => false,
	'data'  => []
);
if (isset($_POST) && !empty($_FILES['file'])) {
    $duoi = explode('.', $_FILES['file']['name']); // tách chuỗi khi gặp dấu .
    $duoi = $duoi[(count($duoi) - 1)]; //lấy ra đuôi file
     //= trim($_FILES['file']['name'], $duoi);
    $name_folder = str_replace('.'.$duoi, '', $_FILES['file']['name']);

    // Kiểm tra xem có phải file ảnh không
    if ($duoi === 'xlsx' || $duoi === 'csv') {
        // tiến hành upload
        $file = 'uploads/' . $_FILES['file']['name'];
        if (file_exists($file)) {
        	$result = array(
					'status' => false,
					'data'  => 'Tệp đã tồn tại trên server'
			);
        }else{
	        if (move_uploaded_file($_FILES['file']['tmp_name'], $file)) {

	        		
	        		//create folder 
					if (!file_exists('uploads/'.$name_folder.'')) {
					    mkdir('uploads/'.$name_folder.'', 0777, true);
					}
					//explode sheet

	        		//basesheets
	        		@$xlsx = SimpleXLSX::parse($file);
	        		$sheetsCount = $xlsx->sheetsCount();
	        		//create array which have sheet
	        		$arr_sheet = [];
	        		for ($i=0; $i < $sheetsCount; $i++) {
	 					$arr_sheet[$i]['name'] = $xlsx->sheetName($i);
	 					$for = $xlsx->rows($i);

	 					file_put_contents('uploads/'.$name_folder.'/sheet_'.$i.'.html', create_html_table($for)); 
	        		}
	        		file_put_contents('uploads/'.$name_folder.'/sheet.json', json_encode($arr_sheet)); 
	        } else { 
					$result = array(
						'status' => false,
						'data'  => 'Không thể upload file'
					);
	        }
        }

    } else {
				$result = array(
					'status' => false,
					'data'  => 'Tải lên đúng định dạng file '
				);
    }
    exit(json_encode($result));
}
