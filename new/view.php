<?php
if(!isset($_GET['file'])){
	exit('File not found');
}else{
	$file = 'uploads/' . $_GET['file'];
	if (!file_exists($file)) {
		exit('File not found');
	}
    $duoi = explode('.', $file); 
    $duoi = $duoi[(count($duoi) - 1)]; 
    $name_folder = str_replace('.'.$duoi, '', $file);

	$sheet = json_decode(file_get_contents(''.$name_folder.'/sheet.json'), true);
}
/*
AJAX split sheet
*/
if(isset($_GET['sheet'])){
	$num = (int) $_GET['sheet'];
	echo file_get_contents(''.$name_folder.'/sheet_'.$num.'.html');
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
<script src="../assets/js/jquery.min.js"></script>

<script src="../assets/js/SimpleTableCellEditor.js"></script>
<link rel="stylesheet" type="text/css" href="../assets/css/bootstrap.min.css">
<div style="padding-top: 1%"></div>
<div class="container">
	<div class="row">
		<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
			<div class="alert">
				<div class="btn-group">
					<center>
						<?php
							for ($i=0; $i < count($sheet); $i++) { 
								echo '<button onclick="load_sheet('.$i.')" type="button" class="btn btn-default">'.$sheet[$i]['name'].'</button>';	
							}
						?>
					</center>
				 
		<br><br><hr>

 
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