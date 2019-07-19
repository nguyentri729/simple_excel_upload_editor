<?php  
	header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-disposition: attachment; filename='.rand().'.xlsx');  
	echo file_get_contents('http://excel.local/done_1.php?id_file=1&sheet=1');

	$url = "https://example.com/file-to-download.zip" ; // URL of the file you want to download

$filename = basename($url); // Getting the base name of the file.

file_put_contents('cac.xlsx');
 ?>  