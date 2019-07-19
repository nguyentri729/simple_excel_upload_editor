
<html>
	<head>
		<script src="assets/js/jquery.min.js"></script>
		<script src="assets/js/jquery.jexcel.js"></script>
		<link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
		<link rel="stylesheet" href="assets/css/jquery.jexcel.css" type="text/css" />
		<script src="assets/js/xlsx.full.min.js"></script>
		<title>Simple excel Editor</title>
	</head>

<body>
	<div style="padding-top: 5%"></div>
	<div class="container-fluid">
		<div class="row">
			<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
				<div class="panel panel-primary">
					<div class="panel-heading">
						<h3 class="panel-title">Simple excel Editor</h3>
					</div>
					<div class="panel-body">
						<form action="" method="POST" role="form">
						

						<div class="form-group">
							<label for="">Chose file</label>
							<input id="file" type="file" name="sortpic" required="" />
						</div>
						<div class="form-group">
							<button id="upload" class="btn btn-primary">Upload</button>
						</div>	
					</form>
					<div class="status alert alert-success" style="display: none;"></div>
					<hr>
						<div id="my"></div>
						<input type="hidden" id="file_name" value=""><br><br>
						<button class="btn btn-success" id="download" style="display: none;">Download</button>

					</div>
				</div>
			</div>
	</div>
            
</body>

<script>

	$('#download').click(function(){
            $.ajax({
                async: false,
                method: 'POST',
                url: "export.php",
                data: {export: $('#my').jexcel('getData', false), name: $('#file_name').val()}
            }).done(function(a) {
            
               	window.location = a;

            }).fail(function(){
               alert('error');
            });
	});


</script>
<script type="text/javascript" src="assets/js/upload.js"></script>
</html>