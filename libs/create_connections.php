<?
	define('SIZE', 32);
	
	include 'functions.php';
	
	function create_connections($world_id){
		$mysqli = new mysqli('localhost','maximus','ltiS950+','adigi');
		
		for($x = 0; $x < SIZE; $x++){
			for($y = 0; $y < SIZE; $y++){
				$neighbors = array();
				$query = "SELECT * FROM grid WHERE x = " . $x . " AND y = " . $y;
				$result = $mysqli->query($query);
				$current_cell = $result->fetch_assoc();
				
				$query = get_neighbors($x,$y) . " AND world_id = " . $world_id;
				$result = $mysqli->query($query);
				if($row = $result->fetch_assoc()){
				do{
					if(($row['x'] + 1) % SIZE == $current_cell['x']){
						$nx = 0;
					} else if($row['x'] == $current_cell['x']){
						$nx = 1;
					} else {
						$nx = 2;
					}
					
					if(($row['y'] + 1) % SIZE == $current_cell['y']){
						$ny = 0;
					} else if($row['y'] == $current_cell['y']){
						$ny = 1;
					} else {
						$ny = 2;
					}
					
					$row['high'] = $row['inst'] >> 13;
					$row['low'] = $row['inst'] & 7;
					
					$neighbors[$nx][$ny] = $row;
				} while($row = $result->fetch_assoc());
				}
				
				$high_bits = $current_cell['inst'] >> 13;
				$low_bits = $current_cell['inst'] & 7;
				
				$low_connection = '';
			}
		}
	}
	
	create_connections(1);
?>