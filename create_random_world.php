<?
	define('REVISION', '0.0.1');
	define('SIZE', 32);
	define('DIMS', 2);
	
	$mysqli = new mysqli('localhost','maximus','ltiS950+','adigi');
	
	$query = "INSERT INTO world (rev,size,dimensions,created_at) VALUES(" . (float)REVISION . "," . SIZE . "," . DIMS . ",NOW())";
	$mysqli->query($query);
	
	$world_id = $mysqli->insert_id;
	
	$world = array();
	for($i = 0; $i < SIZE; $i++){
		for($j = 0; $j < SIZE; $j++){
			$inst = rand(0,pow(2,16));
			$blank = rand(0,2);
			if($blank == 0){
				$world[$i][$j] = 0;
			} else {
				$world[$i][$j] = $inst;
			}
			$energy = rand(0,10);
			
			$query = "INSERT INTO grid (x,y,inst,energy,world_id) VALUES(" . $i . "," . $j . "," . $world[$i][$j] . "," . $energy . "," . $world_id . ")";
			$mysqli->query($query);
		}
	}
?>