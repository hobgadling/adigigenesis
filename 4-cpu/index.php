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
			width: 560px;
		}
		.segment{
			float: left;
			width: 140px;
		}
		.cell{
			float: left;
			border: 1px solid #000;
			width: 10px;
			height: 10px;
			margin: 1px;
			background-color: #fff;
		}
	</style>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
	<script src="jquery.panzoom.min.js"></script>
	<script type="text/javascript">
	grid = new Array();
	segment_info = '';
	grid_segments = new Array();
	
	$(document).ready(function(){
		$.getJSON('get_segments.php',function(data){
			segment_info = data;
			setGridArray();
			setInterval('setGridArray()', 1000);
		});
		$('#container').panzoom();
		$('#container').dblclick(function(){
			$('#container').panzoom("zoom", { silent: true });
		});
	});
	
	function setGridArray(){
		grid_segments = new Array();
		start_character = " ".charCodeAt(0);
		$.each(segment_info,function(segment_key,segment_value){
			grid_segments[segment_key] = '';
			current_key = parseInt(start_character)+parseInt(segment_key);
			$.getJSON('get_grid.php?char=' + current_key,function(data){
				grid_segments[segment_key] += '<div class="segment">';
				$.each(data,function(key,value){
					$.each(value,function(k,v){
						if(v != 0){
							grid_segments[segment_key] += '<div class="cell alive" style="background-color: #' + v.toString(16) + ';"></div>';
						} else {
							grid_segments[segment_key] += '<div class="cell"></div>';
						}
					});
				});
				grid_segments[segment_key] += '</div>';
			});
		});
		
		$(document).ajaxStop(function(){
			$('#container').html('');
			for(i in grid_segments){
				$('#container').html($('#container').html() + grid_segments[i]);
			}
		});
	}
	</script>
</head>
<body>
<div id="container">
	
</div>
</body>
</html>