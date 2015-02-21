<?php
	include 'cpu.php';
	
	ini_set('max_execution_time', 0);
	const SIZE = 40;
	var $grid = array();
	var $segments = array();
	$shmHeaderSize = (PHP_INT_SIZE * 4) + 8;
	
	function neighborsSum($grid,$row_id,$col_id){
		$sum = 0;
		if($row_id == 0){
			if($col_id == 0){
				$sum += $grid[count($grid)-1][count($grid)-1];
				$sum += $grid[count($grid)-1][$col_id];
				$sum += $grid[count($grid)-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[$row_id+1][$col_id+1];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][count($grid)-1];
				$sum += $grid[$row_id][count($grid)-1];
			} else if($col_id == count($grid)-1){
				$sum += $grid[count($grid)-1][$col_id-1];
				$sum += $grid[count($grid)-1][$col_id];
				$sum += $grid[count($grid)-1][0];
				$sum += $grid[$row_id][0];
				$sum += $grid[$row_id+1][0];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			} else {
				$sum += $grid[count($grid)-1][$col_id-1];
				$sum += $grid[count($grid)-1][$col_id];
				$sum += $grid[count($grid)-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[$row_id+1][$col_id+1];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			}
		} else if($row_id == count($grid)-1){
			if($col_id == 0){
				$sum += $grid[$row_id-1][count($grid)-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[0][$col_id+1];
				$sum += $grid[0][$col_id];
				$sum += $grid[0][count($grid)-1];
				$sum += $grid[$row_id][count($grid)-1];
			} else if($col_id == count($grid)-1){
				$sum += $grid[$row_id-1][$col_id-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][0];
				$sum += $grid[$row_id][0];
				$sum += $grid[0][0];
				$sum += $grid[0][$col_id];
				$sum += $grid[0][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			} else {
				$sum += $grid[$row_id-1][$col_id-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[0][$col_id+1];
				$sum += $grid[0][$col_id];
				$sum += $grid[0][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			}
		} else {
			if($col_id == 0){
				$sum += $grid[$row_id-1][count($grid)-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[$row_id+1][$col_id+1];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][count($grid)-1];
				$sum += $grid[$row_id][count($grid)-1];
			} else if($col_id == count($grid)-1){
				$sum += $grid[$row_id-1][$col_id-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][0];
				$sum += $grid[$row_id][0];
				$sum += $grid[$row_id+1][0];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			} else {
				$sum += $grid[$row_id-1][$col_id-1];
				$sum += $grid[$row_id-1][$col_id];
				$sum += $grid[$row_id-1][$col_id+1];
				$sum += $grid[$row_id][$col_id+1];
				$sum += $grid[$row_id+1][$col_id+1];
				$sum += $grid[$row_id+1][$col_id];
				$sum += $grid[$row_id+1][$col_id-1];
				$sum += $grid[$row_id][$col_id-1];
			}
		}
		return $sum;
	}
	
	function tick($grid){
		$newgrid = array();
		$calcgrid = array();
		
		foreach($grid as $row_id => $row){
			foreach($row as $col_id => $cell){
				if($cell > 0){
					$calcgrid[$row_id][$col_id] = 1;
				} else {
					$calcgrid[$row_id][$col_id] = 0;
				}
			}
		}
		
		foreach($grid as $row_id => $row){
			foreach($row as $col_id => $cell){
				switch(neighborsSum($calcgrid,$row_id,$col_id)){
					case 0:
					case 1:
					case 4:
					case 5:
					case 6:
					case 7:
					case 8:
						$newgrid[$row_id][$col_id] = 0;
						break;
					case 3:
						$newgrid[$row_id][$col_id] = rand(0,0xFFFFFF);
						break;
					default:
						$newgrid[$row_id][$col_id] = $grid[$row_id][$col_id];
						break;
				}
			}
		}
		return $newgrid;
	}
	
	for($i = 0; $i < SIZE; $i++){
		for($j = 0; $j < SIZE; $j++){
			if(rand(0,2) == 0){
				$grid[$i][$j] = 0;
			} else {
				$grid[$i][$j] = rand(0,0xFFFFFF);
			}
		}
	}
	
	if(SIZE/10 < 9){
		$segment_num = 1;
		for($i = 0; $i < SIZE; $i += 10){
			for($j = 0; $j < SIZE; $j += 10){
				$segments[$segment_num] = $i . 'x' . $j;
				$segment_num++;
			}
		}
		$chunk_size = 10;
	} else {
		$chunk_size = SIZE / 8;
		for($i = 0; $i < SIZE; $i += $chunk_size){
			for($j = 0; $j < SIZE; $j += $chunk_size){
				$segments[$segment_num] = $i . 'x' . $j;
				$segment_num++;
			}
		}
	}
	
	$key = ftok(getcwd() . '/get_segments.php','a');
	$segment_json = json_encode($segments);
	$shmVarSize = (((strlen(serialize($segment_json))+ (4 * PHP_INT_SIZE)) /4 ) * 4 ) + 4;
	$shm_segment = shm_attach($key,2*($shmHeaderSize+$shmVarSize),0666);
	shm_put_var($shm_segment, 1, $segment_json);
	shm_detach($shm_segment);
	
	while(true){
		$grid = tick($grid);
		$char = ord(" ");
		
		foreach($segments as $id=>$segment){
			$start_coords = explode('x', $segment);
			$grid_chunk = array();
			$index = 0;
			for($i = $start_coords[0];$i < $start_coords[0] + $chunk_size;$i++){
				$grid_chunk[$index] = array();
				for($j = $start_coords[1]; $j < $start_coords[1] + $chunk_size;$j++){
					$grid_chunk[$index][] = $grid[$i][$j];
				}
				$index++;
			}
			$grid_json = json_encode($grid_chunk);
			$key = ftok(getcwd() . '/phpinfo.php', chr($char + $id));
			$shmVarSize = (((strlen(serialize($grid_json))+ (4 * PHP_INT_SIZE)) /4 ) * 4 ) + 4;
			$shm_segment = shm_attach($key,2*($shmHeaderSize+$shmVarSize),0666);
			shm_put_var($shm_segment, 1, $grid_json);
			shm_detach($shm_segment);
		}
		
		sleep(1);
	}
?>