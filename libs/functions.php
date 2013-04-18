<?
	function get_neighbors($x,$y){
		$query = "SELECT * FROM grid WHERE ";
		for($i = -1; $i < 2; $i++){
			for($j = -1; $j < 2; $j++){
				if($i == 0 && $j == 0){
					
				} else {
					if(($x + $i) < 0){
						$newx = SIZE + ($x + $i);
					} else {
						$newx = ($x + $i);
					}
					
					if(($y + $j) < 0){
						$newy = SIZE + ($y + $j);
					} else {
						$newy = ($y + $j);
					}
					
					$query .= "(x = " . $newx . " AND y = " . $newy . ") OR ";
				}
			}
		}
		
		return substr($query,0,-3);
	}
?>