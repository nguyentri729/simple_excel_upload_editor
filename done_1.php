<?php
if(!isset($_GET['id_file'])){
	exit('File not found');
}else{
	$id_file  = (int)$_GET['id_file'];
}
require 'vendor/autoload.php';
//uploads/baocao.xlsx
$xlsx = SimpleXLSX::parse('sheet_test.xlsx');
//$xlsx = SimpleXLSX::parse('uploads/baocao.xlsx');
$sheetsCount = $xlsx->sheetsCount() ;

/*
AJAX split sheet
*/
if(isset($_GET['sheet'])){
	$sheet = (int) $_GET['sheet'];
	if ($xlsx) {
		echo '<table border="1" cellpadding="3" style="border-collapse: collapse" id="myTable">';
		$for = $xlsx->rows($sheet);
		foreach( $for as $r ) {

			echo '<tr><td class="editMe">'.implode('</td><td class="editMe">', $r ).'</td></tr>';

		}
		echo '</table>';
		
	} else {
		echo SimpleXLSX::parseError();
	}
	exit();
}
?>
<!DOCTYPE html>
<html>
<head>
	<title>Scritp Test</title>
</head>
<body>
<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" 
        integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" 
        crossorigin="anonymous">
</script>
<script src="assets/js/jquery.min.js"></script>

<script src="assets/js/SimpleTableCellEditor.js"></script>
<link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
<div style="padding-top: 1%"></div>
<div class="container">
	<div class="row">
		<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
			<div class="alert">
				<div class="btn-group">
					<center>
	<?php

	if($sheetsCount > 0){

		for ($i=0; $i < $sheetsCount; $i++) { 
			echo '<button onclick="load_sheet('.$i.')" type="button" class="btn btn-default">'.$xlsx->sheetName($i).'</button>';
			
			
		}


	/*	if(!isset($_GET['sheet']) && $sheetsCount > 1){
			
		}elseif (isset($_GET['sheet']) && $sheetsCount == 1) {
			
		}else{
			
		}*/
	}
	?>
	</center>
				 
		<br><br>
</div>
</div>
</div>		 
<div class="col-md-12">
<center>
	<div id="table_ajax"></div>
</center>
</div>
				</div>
			</div>
		</div>
	</div>

</div>
<script type="text/javascript">
function enable_tabledit(){
	  editor = new SimpleTableCellEditor("myTable");
  editor.SetEditableClass("editMe");

  $('#myTable').on("cell:edited", function (event) {
    console.log(`'${event.oldValue}' changed to '${event.newValue}'`);
  });
}
function load_sheet(id){
	$('#table_ajax').html('Đang load sheet...');
	$.get('', {sheet: id}).done(function(a){
		$('#table_ajax').html(a);
		enable_tabledit();
	}).fail(function(){	
		$('#table_ajax').html('Khong the load sheet...');
		alert('Ko the load sheet');
	})
}
$('button').click(function(){
	$('button').prop("disabled", false).removeClass('btn-primary').addClass('btn-default');
	$(this).prop("disabled", true).removeClass('btn-default').addClass('btn-primary');
});
</script>
</body>
</html>