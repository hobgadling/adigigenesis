<!DOCTYPE html>
<html>
<head>
	<title></title>
	<style type="text/css">
		body{
			margin: 0;
			padding: 0;
		}
		#container{
			margin: 0 auto;
			width: 1000px;
		}
		.cell{
			float: left;
			border: 1px solid #000;
			width: 20px;
			height: 20px;
			margin: 1px;
			background-color: #fff;
		}
		.alive{
			background-color: #000;
		}
	</style>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
	<script type="text/javascript">
	grid = new Array();
	
	$(document).ready(function(){
		setGridArray();
		setInterval('setGridArray()', 1000);
	});
	
	function setGridArray(){
		grid_html = '';
		$.getJSON('get_grid.php',function(data){
			$.each(data,function(key,value){
				$.each(value,function(k,v){
					if(v == 1){
						grid_html += '<div class="cell alive"></div>';
					} else {
						grid_html += '<div class="cell"></div>';
					}
				});
			});
			$('#container').html(grid_html);
		});
		
	}
	</script>
</head>
<body>
<div id="container">
	
</div>
</body>
</html>