<?php
	include 'cpu.php';
	
	ini_set('max_execution_time', 0);
	const SIZE = 40;
	$GLOBALS['grid'] = array();
	$segments = array();
	$shmHeaderSize = (PHP_INT_SIZE * 4) + 8;
	
	
	
	for($i = 0; $i < SIZE; $i++){
		for($j = 0; $j < SIZE; $j++){
			if(rand(0,5) == 0){
				$GLOBALS['grid'][$i][$j] = 0;
			} else {
				$GLOBALS['grid'][$i][$j] = rand(0,0xFFFFFF);
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
		//$GLOBALS['grid'] = tick($GLOBALS['grid']);
		step6502();
		$char = ord(" ");
		
		foreach($segments as $id=>$segment){
			$start_coords = explode('x', $segment);
			$grid_chunk = array();
			$index = 0;
			for($i = $start_coords[0];$i < $start_coords[0] + $chunk_size;$i++){
				$grid_chunk[$index] = array();
				for($j = $start_coords[1]; $j < $start_coords[1] + $chunk_size;$j++){
					$grid_chunk[$index][] = $GLOBALS['grid'][$i][$j];
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
	}
?>